<?php

namespace SmartCustomer\Reviews\Test\Unit\Model;

use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;
use SmartCustomer\Reviews\Helper\Data;
use SmartCustomer\Reviews\Model\OrderLocaleObserver;

class OrderLocaleObserverTest extends TestCase
{
    public function testPlacingAnOrderSavesTheLocaleOfItsStoreView(): void
    {
        $helper = $this->createMock(Data::class);
        $helper->expects($this->once())->method('getStoreLocale')->with(7)->willReturn('ja_JP');
        $order = $this->createMock(Order::class);
        $order->method('getStoreId')->willReturn(7);
        $order->expects($this->once())->method('setData')->with('smartcustomer_locale', 'ja_JP');

        (new OrderLocaleObserver($helper))->execute(new Observer(['event' => new Event(['order' => $order])]));
    }
}
