<?php

namespace SmartCustomer\Reviews\Cron;

use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use SmartCustomer\Reviews\Helper\Data;
use SmartCustomer\Reviews\Helper\SyncOrders;
use SmartCustomer\Reviews\Model\Outbox;

class ProcessOutbox
{
    protected $_dataHelper;
    protected $_logger;
    protected $_outbox;
    protected $_storeManager;
    protected $_sync;

    public function __construct(
        Data $dataHelper,
        SyncOrders $sync,
        Outbox $outbox,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->_dataHelper = $dataHelper;
        $this->_sync = $sync;
        $this->_outbox = $outbox;
        $this->_storeManager = $storeManager;
        $this->_logger = $logger;
    }

    public function execute()
    {
        try {
            $this->_outbox->recoverStaleDeliveries();

            foreach ($this->_outbox->getReadyIds($this->getDeliverableStoreIds()) as $entityId) {
                $row = $this->_outbox->claim($entityId);
                if (!$row) {
                    continue;
                }

                $this->deliver($row);
            }

            $this->_outbox->purgeProcessed();
        } catch (\Throwable $e) {
            $this->_logger->error('SmartCustomer outbox cron failed: ' . $e->getMessage());
        }
    }

    protected function getDeliverableStoreIds()
    {
        $storeIds = [];
        foreach ($this->_storeManager->getStores() as $store) {
            $storeId = (int) $store->getId();
            if ($this->isDeliverable($storeId)) {
                $storeIds[] = $storeId;
            }
        }

        return $storeIds;
    }

    protected function isDeliverable($storeId)
    {
        return !empty($this->_dataHelper->getConfig('enabled', $storeId))
            && !empty($this->_dataHelper->getConfig('api_key', $storeId))
            && !empty($this->_dataHelper->getConfig('api_secret', $storeId));
    }

    protected function deliver(array $row)
    {
        $entityId = (int) $row['entity_id'];
        $orderId = (int) $row['order_id'];
        $storeId = (int) $row['store_id'];

        try {
            $apiKey = $this->_dataHelper->getConfig('api_key', $storeId);
            $apiSecret = $this->_dataHelper->getConfig('api_secret', $storeId);

            $successful = $this->_sync->syncOrders([
                'api_key' => $apiKey,
                'api_secret' => urlencode($this->_dataHelper->encrypt($apiSecret, $storeId)),
                'id' => $orderId,
                'store_id' => $storeId
            ]);

            if (!$successful) {
                throw new \RuntimeException('Remote endpoint did not acknowledge the order');
            }

            $this->_outbox->markSent($entityId);
        } catch (\Throwable $e) {
            $attempts = (int) $row['attempts'];
            if ($attempts >= Outbox::MAX_ATTEMPTS) {
                $this->_outbox->markDiscarded($entityId, 'Gave up after ' . $attempts . ' attempts: ' . $e->getMessage());
            } else {
                $this->_outbox->markRetry($entityId, $attempts, $e->getMessage());
            }
            $this->_logger->warning(
                'SmartCustomer delivery failed for order ' . $orderId . ': ' . $e->getMessage()
            );
        }
    }
}
