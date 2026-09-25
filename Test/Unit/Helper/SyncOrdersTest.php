<?php

namespace SmartCustomer\Reviews\Test\Unit\Helper;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SmartCustomer\Reviews\Helper\Data;
use SmartCustomer\Reviews\Helper\HttpClient;
use SmartCustomer\Reviews\Helper\SyncOrders;

class SyncOrdersTest extends TestCase
{
    public function testRegisteredCustomerOrderIsSentWithTheCustomerDetailsStoredOnTheOrder(): void
    {
        $order = $this->createStub(Order::class);
        $order->method('getCustomerId')->willReturn(7);
        $order->method('getCustomerEmail')->willReturn('jane@example.com');
        $order->method('getCustomerFirstname')->willReturn('Jane');
        $order->method('getCustomerLastname')->willReturn('Doe');
        $order->method('getCreatedAt')->willReturn('2026-09-24 10:00:00');
        $order->method('getRealOrderId')->willReturn('000000001');

        $collection = $this->createStub(Collection::class);
        $collection->method('addAttributeToSelect')->willReturnSelf();
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$order]));
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $sent = null;
        $http = $this->createMock(HttpClient::class);
        $http->expects($this->once())->method('request')
            ->willReturnCallback(function ($url, $method, $origin, $data) use (&$sent) {
                $sent = $data;
                return ['code' => 202];
            });

        $sync = new SyncOrders($factory, $this->createStub(Data::class), $http, $this->createStub(LoggerInterface::class));

        $this->assertTrue($sync->syncOrders(['api_key' => 'key', 'api_secret' => 'secret', 'id' => 1]));
        $this->assertSame(['email' => 'jane@example.com', 'first_name' => 'Jane', 'last_name' => 'Doe'], $sent['customer']);
    }

    #[DataProvider('fromDates')]
    public function testHistoricalSyncRunsOnlyForAValidFromDate(string $from, bool $valid): void
    {
        $order = $this->createStub(Order::class);
        $order->method('getCreatedAt')->willReturn('2026-09-24 10:00:00');

        $collection = $this->createStub(Collection::class);
        $collection->method('addAttributeToSelect')->willReturnSelf();
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$order]));
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $http = $this->createMock(HttpClient::class);
        $http->expects($valid ? $this->once() : $this->never())->method('request')
            ->with($this->stringEndsWith('/orders/sync'))
            ->willReturn(['code' => 202]);

        $sync = new SyncOrders($factory, $this->createStub(Data::class), $http, $this->createStub(LoggerInterface::class));

        $this->assertSame($valid, $sync->syncOrders(['api_key' => 'key', 'api_secret' => 'secret', 'from' => $from]));
    }

    public static function fromDates(): array
    {
        return [
            'valid date' => ['2026-07-24', true],
            'day past the end of the month' => ['2026-02-30', false],
            'not a date' => ['garbage', false],
        ];
    }
}
