<?php

namespace SmartCustomer\Reviews\Test\Unit\Model;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SmartCustomer\Reviews\Helper\Data;
use SmartCustomer\Reviews\Model\Outbox;
use SmartCustomer\Reviews\Model\SyncObserver;

class SyncObserverTest extends TestCase
{
    public function testResavingAnOrderWithoutAStatusChangeDoesNotQueueItAgain(): void
    {
        $outbox = $this->createMock(Outbox::class);
        $outbox->expects($this->never())->method('enqueue');

        $this->observe($outbox, 'complete', 'complete');
    }

    public function testAStatusChangeQueuesTheOrder(): void
    {
        $outbox = $this->createMock(Outbox::class);
        $outbox->expects($this->once())->method('enqueue')->with(5, 1);

        $this->observe($outbox, 'pending', 'complete');
    }

    public function testAnOrderWithNoPreviousStatusIsQueued(): void
    {
        $outbox = $this->createMock(Outbox::class);
        $outbox->expects($this->once())->method('enqueue')->with(5, 1);

        $this->observe($outbox, null, 'pending');
    }

    private function observe(Outbox $outbox, ?string $previousStatus, string $status): void
    {
        $order = $this->createStub(Order::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getEntityId')->willReturn(5);
        $order->method('getStatus')->willReturn($status);
        $order->method('getOrigData')->willReturnCallback(
            static fn($key) => $key === 'status' ? $previousStatus : null
        );

        // Real Event/Observer: the observer reads the order through Magento's
        // magic getters, which a stub does not carry.
        $observer = new Observer(['event' => new Event(['order' => $order])]);

        $data = $this->createStub(Data::class);
        $data->method('getConfig')->willReturn('1');

        (new SyncObserver($data, $outbox, $this->createStub(LoggerInterface::class)))->execute($observer);
    }
}
