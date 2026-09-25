<?php

namespace SmartCustomer\Reviews\Cron;

use Psr\Log\LoggerInterface;
use SmartCustomer\Reviews\Helper\Data;
use SmartCustomer\Reviews\Helper\SyncOrders;
use SmartCustomer\Reviews\Model\Outbox;

class ProcessOutbox
{
    protected $_dataHelper;
    protected $_logger;
    protected $_outbox;
    protected $_sync;

    public function __construct(
        Data $dataHelper,
        SyncOrders $sync,
        Outbox $outbox,
        LoggerInterface $logger
    ) {
        $this->_dataHelper = $dataHelper;
        $this->_sync = $sync;
        $this->_outbox = $outbox;
        $this->_logger = $logger;
    }

    public function execute()
    {
        try {
            $this->_outbox->recoverStaleDeliveries();

            foreach ($this->_outbox->getReadyIds() as $entityId) {
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

    protected function deliver(array $row)
    {
        $entityId = (int) $row['entity_id'];
        $orderId = (int) $row['order_id'];
        $storeId = (int) $row['store_id'];

        try {
            $apiKey = $this->_dataHelper->getConfig('api_key', $storeId);
            $apiSecret = $this->_dataHelper->getConfig('api_secret', $storeId);
            $enabled = $this->_dataHelper->getConfig('enabled', $storeId);

            if (empty($enabled) || empty($apiKey) || empty($apiSecret)) {
                $this->_outbox->markDiscarded(
                    $entityId,
                    'Integration is disabled or missing credentials for store ' . $storeId
                );
                return;
            }

            $successful = $this->_sync->syncOrders([
                'api_key' => $apiKey,
                'api_secret' => urlencode($this->_dataHelper->encrypt($apiSecret, $storeId)),
                'id' => $orderId
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
