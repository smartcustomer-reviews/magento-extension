<?php

namespace SmartCustomer\Reviews\Test\Unit\Cron;

use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SmartCustomer\Reviews\Cron\ProcessOutbox;
use SmartCustomer\Reviews\Helper\Data;
use SmartCustomer\Reviews\Helper\SyncOrders;
use SmartCustomer\Reviews\Model\Outbox;

class ProcessOutboxTest extends TestCase
{
    #[DataProvider('failedAttempts')]
    public function testFailedDeliveryIsRetriedUntilTheAttemptLimit(int $attempts, bool $discarded): void
    {
        $data = $this->createStub(Data::class);
        $data->method('getConfig')->willReturn('1');
        $data->method('encrypt')->willReturn('sj#secret');

        $sync = $this->createStub(SyncOrders::class);
        $sync->method('syncOrders')->willReturn(false);

        $outbox = $this->createMock(Outbox::class);
        $outbox->method('getReadyIds')->willReturn([10]);
        $outbox->method('claim')->willReturn(['entity_id' => 10, 'order_id' => 5, 'store_id' => 1, 'attempts' => $attempts]);
        $outbox->expects($discarded ? $this->never() : $this->once())->method('markRetry');
        $outbox->expects($discarded ? $this->once() : $this->never())->method('markDiscarded');
        $outbox->expects($this->once())->method('purgeProcessed');

        $this->process($data, $sync, $outbox, [1]);
    }

    public static function failedAttempts(): array
    {
        return [
            'first failure' => [1, false],
            'last retry' => [Outbox::MAX_ATTEMPTS - 1, false],
            'limit reached' => [Outbox::MAX_ATTEMPTS, true],
        ];
    }

    public function testDeliveriesForADisabledStoreAreLeftQueuedInsteadOfDiscarded(): void
    {
        $data = $this->createStub(Data::class);
        $data->method('getConfig')->willReturnCallback(
            static fn($config) => $config === 'enabled' ? null : 'value'
        );

        $outbox = $this->createMock(Outbox::class);
        $outbox->expects($this->once())->method('getReadyIds')->with([])->willReturn([]);
        $outbox->expects($this->never())->method('claim');
        $outbox->expects($this->never())->method('markDiscarded');
        $outbox->expects($this->never())->method('markRetry');

        $sync = $this->createMock(SyncOrders::class);
        $sync->expects($this->never())->method('syncOrders');

        $this->process($data, $sync, $outbox, [1]);
    }

    public function testADisabledStoreDoesNotHoldBackTheStoresThatAreStillOn(): void
    {
        $data = $this->createStub(Data::class);
        $data->method('getConfig')->willReturnCallback(
            static fn($config, $storeId) => $config === 'enabled' && (int) $storeId === 1 ? null : 'value'
        );
        $data->method('encrypt')->willReturn('sj#secret');

        // The batch is capped, so a paused store has to be filtered out of the
        // query rather than skipped after it: rows it left behind would sit at
        // the front of every batch and no other store would ever be reached.
        $outbox = $this->createMock(Outbox::class);
        $outbox->expects($this->once())->method('getReadyIds')->with([2])->willReturn([10]);
        $outbox->method('claim')->willReturn(['entity_id' => 10, 'order_id' => 5, 'store_id' => 2, 'attempts' => 1]);
        $outbox->expects($this->once())->method('markSent')->with(10);

        $sync = $this->createStub(SyncOrders::class);
        $sync->method('syncOrders')->willReturn(true);

        $this->process($data, $sync, $outbox, [1, 2]);
    }

    public function testDeliveryIsScopedToTheStoreTheOrderBelongsTo(): void
    {
        $data = $this->createStub(Data::class);
        $data->method('getConfig')->willReturn('1');
        $data->method('encrypt')->willReturn('sj#secret');

        $outbox = $this->createStub(Outbox::class);
        $outbox->method('getReadyIds')->willReturn([10]);
        $outbox->method('claim')->willReturn(['entity_id' => 10, 'order_id' => 5, 'store_id' => 4, 'attempts' => 1]);

        $options = null;
        $sync = $this->createStub(SyncOrders::class);
        $sync->method('syncOrders')->willReturnCallback(function ($given) use (&$options) {
            $options = $given;
            return true;
        });

        $this->process($data, $sync, $outbox, [4]);

        $this->assertSame(4, $options['store_id']);
    }

    private function process(Data $data, SyncOrders $sync, Outbox $outbox, array $storeIds): void
    {
        $stores = [];
        foreach ($storeIds as $storeId) {
            $store = $this->createStub(StoreInterface::class);
            $store->method('getId')->willReturn($storeId);
            $stores[] = $store;
        }

        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn($stores);

        (new ProcessOutbox($data, $sync, $outbox, $storeManager, $this->createStub(LoggerInterface::class)))->execute();
    }
}
