<?php
namespace SmartCustomer\Reviews\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Psr\Log\LoggerInterface;
use SmartCustomer\Reviews\Model\Config;

class SyncOrders extends AbstractHelper
{
    protected $_dataHelper;
    protected $_httpClient;
    protected $_logger;
    protected $_orderCollectionFactory;

    public function __construct(
        CollectionFactory $orderCollectionFactory,
        Data $helper,
        HttpClient $httpClient,
        LoggerInterface $logger
    ) {
        $this->_dataHelper = $helper;
        $this->_httpClient = $httpClient;
        $this->_logger = $logger;
        $this->_orderCollectionFactory = $orderCollectionFactory;
    }

    public function syncOrders($options = [])
    {
        $options = $options + [
            'api_key'        => null,
            'api_secret'    => null,
            'from'            => null,
            'id'            => null
        ];
        if (empty($options['api_key']) || empty($options['api_secret']) || (empty($options['from']) && empty($options['id']))) {
            return false;
        }
        
        $collection = $this->_orderCollectionFactory->create()
        ->addAttributeToSelect('*');
        if (!empty($options['from']) && $this->_checkDate($options['from'])) {
            $collection->addFieldToFilter('created_at', ['gteq' => (new \DateTime($options['from']))->format('Y-m-d H:i:s')])
            ->addFieldToFilter('status', ['eq' => 'complete'])
            ->setOrder(
                'created_at',
                'desc'
            );
        } elseif (!empty($options['id']) && is_numeric($options['id'])) {
            $collection->addFieldToFilter('entity_id', ['eq' => (int) $options['id']]);
        } else {
            return false;
        }

        $timezone = $this->_dataHelper->getConfig('general/locale/timezone');
        $timezone = $timezone ? new \DateTimeZone($timezone) : null;
        
        $endpoint = Config::ENDPOINT_URL . (empty($options['id']) ? '/orders/sync' : '/orders');
        $processed = 0;
        $successful = true;
        foreach ($collection as $order) {
            $createdDate = \DateTime::createFromFormat('Y-m-d H:i:s', $order->getCreatedAt());
            if ($timezone) {
                $createdDate->setTimezone($timezone);
            }

            $data = [
                'created'    => $createdDate->format('c'),
                'currency'    => $order->getOrderCurrencyCode(),
                'customer'    => [
                    'email'            => $order->getCustomerEmail(),
                    'first_name'    => $order->getCustomerFirstname(),
                    'last_name'        => $order->getCustomerLastname()
                ],
                'order_id'    => $order->getRealOrderId(),
                'shipping'    => $order->getBaseShippingAmount(),
                'status'    => $order->getStatus(),
                'subtotal'    => $order->getBaseSubtotal(),
                'tax'        => $order->getTaxAmount(),
                'total'        => $order->getBaseGrandTotal()
            ];

            $response = $this->_httpClient->request($endpoint, 'POST', null, $data, [
                'api_key'        => $options['api_key'],
                'api_secret'    => $options['api_secret']
            ]);

            $processed++;
            $responseCode = is_array($response) && isset($response['code'])
                ? (int) $response['code']
                : 0;
            if ($responseCode < 200 || $responseCode >= 300) {
                $successful = false;
            }
        }

        return $processed > 0 && $successful;
    }
    
    private function _checkDate($date)
    {
        // Round-trip check: DateTime::getLastErrors() returns false instead of an array since PHP 8.2
        $dt = \DateTime::createFromFormat('Y-m-d', $date);
        return $dt !== false && $dt->format('Y-m-d') === $date;
    }
}
