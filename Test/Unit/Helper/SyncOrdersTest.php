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

        $this->assertTrue($sync->syncOrders(['api_key' => 'key', 'api_secret' => 'secret', 'id' => 1, 'store_id' => 1]));
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

        $this->assertSame($valid, $sync->syncOrders(['api_key' => 'key', 'api_secret' => 'secret', 'from' => $from, 'store_id' => 1]));
    }

    public static function fromDates(): array
    {
        return [
            'valid date' => ['2026-07-24', true],
            'day past the end of the month' => ['2026-02-30', false],
            'not a date' => ['garbage', false],
        ];
    }

    public function testSyncIsFilteredToTheStoreViewItWasStartedFor(): void
    {
        $filters = [];
        $collection = $this->createStub(Collection::class);
        $collection->method('addAttributeToSelect')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('addFieldToFilter')->willReturnCallback(
            function ($field, $condition) use (&$filters, $collection) {
                $filters[$field] = $condition;
                return $collection;
            }
        );
        $collection->method('getIterator')->willReturn(new \ArrayIterator([]));
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $sync = new SyncOrders(
            $factory,
            $this->createStub(Data::class),
            $this->createStub(HttpClient::class),
            $this->createStub(LoggerInterface::class)
        );
        $sync->syncOrders(['api_key' => 'key', 'api_secret' => 'secret', 'from' => '2026-07-24', 'store_id' => 2]);

        $this->assertSame(['eq' => 2], $filters['store_id']);
    }

    public function testSyncIsRefusedWithoutAStoreView(): void
    {
        $factory = $this->createStub(CollectionFactory::class);
        $http = $this->createMock(HttpClient::class);
        $http->expects($this->never())->method('request');

        $sync = new SyncOrders($factory, $this->createStub(Data::class), $http, $this->createStub(LoggerInterface::class));

        $this->assertFalse($sync->syncOrders(['api_key' => 'key', 'api_secret' => 'secret', 'from' => '2026-07-24']));
    }

    public function testAmountsAndCurrencyCodeShareTheBaseCurrency(): void
    {
        $order = $this->createStub(Order::class);
        $order->method('getCreatedAt')->willReturn('2026-09-24 10:00:00');
        $order->method('getRealOrderId')->willReturn('000000102');
        $order->method('getOrderCurrencyCode')->willReturn('EUR');
        $order->method('getBaseCurrencyCode')->willReturn('USD');
        $order->method('getBaseSubtotal')->willReturn('100.0000');
        $order->method('getBaseShippingAmount')->willReturn('10.0000');
        $order->method('getBaseTaxAmount')->willReturn('17.0000');
        $order->method('getTaxAmount')->willReturn('15.3000');
        $order->method('getBaseGrandTotal')->willReturn('127.0000');

        $collection = $this->createStub(Collection::class);
        $collection->method('addAttributeToSelect')->willReturnSelf();
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('getIterator')->willReturn(new \ArrayIterator([$order]));
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        $sent = null;
        $http = $this->createStub(HttpClient::class);
        $http->method('request')->willReturnCallback(function ($url, $method, $origin, $data) use (&$sent) {
            $sent = $data;
            return ['code' => 202];
        });

        $sync = new SyncOrders($factory, $this->createStub(Data::class), $http, $this->createStub(LoggerInterface::class));
        $sync->syncOrders(['api_key' => 'key', 'api_secret' => 'secret', 'id' => 16, 'store_id' => 1]);

        $this->assertSame('USD', $sent['currency']);
        $this->assertSame('100.0000', $sent['subtotal']);
        $this->assertSame('10.0000', $sent['shipping']);
        $this->assertSame('17.0000', $sent['tax']);
        $this->assertSame('127.0000', $sent['total']);
    }
}
