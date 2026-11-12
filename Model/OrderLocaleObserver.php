<?php
namespace SmartCustomer\Reviews\Model;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use SmartCustomer\Reviews\Helper\Data;

class OrderLocaleObserver implements ObserverInterface
{
    protected $_dataHelper;

    public function __construct(Data $helper)
    {
        $this->_dataHelper = $helper;
    }

    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        $order->setData(Data::ORDER_LOCALE_FIELD, $this->_dataHelper->getStoreLocale($order->getStoreId()));

        return $this;
    }
}
