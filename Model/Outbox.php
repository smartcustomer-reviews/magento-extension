<?php

namespace SmartCustomer\Reviews\Model;

use Magento\Framework\App\ResourceConnection;

class Outbox
{
    const TABLE_NAME = 'smartcustomer_reviews_outbox';
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_RETRY = 'retry';
    const STATUS_SENT = 'sent';
    const STATUS_DISCARDED = 'discarded';

    const BATCH_SIZE = 50;
    const PROCESSING_TIMEOUT_SECONDS = 600;
    const SENT_RETENTION_DAYS = 7;
    const DISCARDED_RETENTION_DAYS = 30;
    const MAX_RETRY_DELAY_SECONDS = 3600;
    // About two days of retries (the delay grows to hourly), then the delivery is discarded
    const MAX_ATTEMPTS = 48;

    protected $_resource;

    public function __construct(ResourceConnection $resource)
    {
        $this->_resource = $resource;
    }

    public function enqueue($orderId, $storeId)
    {
        $now = gmdate('Y-m-d H:i:s');
        $connection = $this->_resource->getConnection();
        $connection->insertOnDuplicate(
            $this->getTableName(),
            [
                'order_id' => (int) $orderId,
                'store_id' => (int) $storeId,
                'status' => self::STATUS_PENDING,
                'attempts' => 0,
                'available_at' => $now,
                'updated_at' => $now,
                'processed_at' => null,
                'last_error' => null
            ],
            [
                'store_id',
                'status',
                'attempts',
                'available_at',
                'updated_at',
                'processed_at',
                'last_error'
            ]
        );
    }

    public function recoverStaleDeliveries()
    {
        $now = gmdate('Y-m-d H:i:s');
        $cutoff = gmdate('Y-m-d H:i:s', time() - self::PROCESSING_TIMEOUT_SECONDS);

        return $this->_resource->getConnection()->update(
            $this->getTableName(),
            [
                'status' => self::STATUS_RETRY,
                'available_at' => $now,
                'updated_at' => $now,
                'last_error' => 'Recovered delivery after worker timeout'
            ],
            [
                'status = ?' => self::STATUS_PROCESSING,
                'updated_at < ?' => $cutoff
            ]
        );
    }

    public function getReadyIds($limit = self::BATCH_SIZE)
    {
        $connection = $this->_resource->getConnection();
        $select = $connection->select()
            ->from($this->getTableName(), ['entity_id'])
            ->where('status IN (?)', [self::STATUS_PENDING, self::STATUS_RETRY])
            ->where('available_at <= ?', gmdate('Y-m-d H:i:s'))
            ->order('available_at ASC')
            ->order('entity_id ASC')
            ->limit((int) $limit);

        return array_map('intval', $connection->fetchCol($select));
    }

    public function claim($entityId)
    {
        $connection = $this->_resource->getConnection();
        $now = gmdate('Y-m-d H:i:s');
        $affected = $connection->update(
            $this->getTableName(),
            [
                'status' => self::STATUS_PROCESSING,
                'updated_at' => $now
            ],
            [
                'entity_id = ?' => (int) $entityId,
                'status IN (?)' => [self::STATUS_PENDING, self::STATUS_RETRY],
                'available_at <= ?' => $now
            ]
        );

        if (!$affected) {
            return null;
        }

        $select = $connection->select()
            ->from($this->getTableName())
            ->where('entity_id = ?', (int) $entityId)
            ->limit(1);
        $row = $connection->fetchRow($select);
        if (!$row) {
            return null;
        }

        $row['attempts'] = (int) $row['attempts'] + 1;
        $connection->update(
            $this->getTableName(),
            ['attempts' => $row['attempts']],
            [
                'entity_id = ?' => (int) $entityId,
                'status = ?' => self::STATUS_PROCESSING
            ]
        );

        return $row;
    }

    public function markSent($entityId)
    {
        $now = gmdate('Y-m-d H:i:s');
        return $this->updateClaimed($entityId, [
            'status' => self::STATUS_SENT,
            'processed_at' => $now,
            'updated_at' => $now,
            'last_error' => null
        ]);
    }

    public function markDiscarded($entityId, $reason)
    {
        $now = gmdate('Y-m-d H:i:s');
        return $this->updateClaimed($entityId, [
            'status' => self::STATUS_DISCARDED,
            'processed_at' => $now,
            'updated_at' => $now,
            'last_error' => $this->truncateError($reason)
        ]);
    }

    public function markRetry($entityId, $attempts, $error)
    {
        $exponent = min(max((int) $attempts - 1, 0), 6);
        $delay = min(60 * (2 ** $exponent), self::MAX_RETRY_DELAY_SECONDS);
        $now = gmdate('Y-m-d H:i:s');

        return $this->updateClaimed($entityId, [
            'status' => self::STATUS_RETRY,
            'available_at' => gmdate('Y-m-d H:i:s', time() + $delay),
            'updated_at' => $now,
            'last_error' => $this->truncateError($error)
        ]);
    }

    public function purgeProcessed()
    {
        return $this->purge(self::STATUS_SENT, self::SENT_RETENTION_DAYS)
            + $this->purge(self::STATUS_DISCARDED, self::DISCARDED_RETENTION_DAYS);
    }

    protected function purge($status, $retentionDays)
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - ($retentionDays * 86400));
        return $this->_resource->getConnection()->delete(
            $this->getTableName(),
            [
                'status = ?' => $status,
                'processed_at < ?' => $cutoff
            ]
        );
    }

    protected function updateClaimed($entityId, array $data)
    {
        return $this->_resource->getConnection()->update(
            $this->getTableName(),
            $data,
            [
                'entity_id = ?' => (int) $entityId,
                'status = ?' => self::STATUS_PROCESSING
            ]
        );
    }

    protected function truncateError($error)
    {
        return function_exists('mb_substr')
            ? mb_substr((string) $error, 0, 2000)
            : substr((string) $error, 0, 2000);
    }

    protected function getTableName()
    {
        return $this->_resource->getTableName(self::TABLE_NAME);
    }
}
