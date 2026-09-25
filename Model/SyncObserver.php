<?php
namespace SmartCustomer\Reviews\Model;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use SmartCustomer\Reviews\Helper\Data;
use Psr\Log\LoggerInterface;

class SyncObserver implements ObserverInterface
{
    protected $_dataHelper;
    protected $_outbox;
    protected $_logger;
    
    public function __construct(
        Data $helper,
        Outbox $outbox,
        LoggerInterface $logger
    ) {
        $this->_dataHelper = $helper;
        $this->_outbox = $outbox;
        $this->_logger = $logger;
    }
    
    public function execute(
        Observer $observer
    ) {
        $order = $observer->getEvent()->getOrder();
        
        $storeId = $order->getStoreId();
        
        if (empty($storeId)) {
            return $this;
        }
        
        $apiKey = $this->_dataHelper->getConfig('api_key', $storeId);
        $apiSecret = $this->_dataHelper->getConfig('api_secret', $storeId);
        $enabled = $this->_dataHelper->getConfig('enabled', $storeId);

        if (empty($enabled) || empty($apiKey) || empty($apiSecret)) {
            return $this;
        }
        
        $id = $order->getEntityId();
        
        try {
            $this->_outbox->enqueue($id, $storeId);
        } catch (\Throwable $e) {
            // Order persistence must remain fail-open. Magento cannot use the
            // remote integration if even the local outbox write is allowed to
            // invalidate an otherwise successful order save.
            $this->_logger->error(
                'Unable to enqueue SmartCustomer order ' . $id . ': ' . $e->getMessage()
            );
        }
        
        return $this;
    }
}
