<?php

namespace SmartCustomer\Reviews\Test\Unit\Controller\Adminhtml\Index;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Backend\Model\View\Result\RedirectFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use SmartCustomer\Reviews\Controller\Adminhtml\Index\Disconnect;
use SmartCustomer\Reviews\Controller\Adminhtml\Index\Index;
use SmartCustomer\Reviews\Helper\Data;

class ConnectionStateTest extends TestCase
{
    public function testAccessMarksOnlyThisStoreAsConnected(): void
    {
        $helper = $this->helperExpectingActive('1');

        (new Index($this->context(), $helper, $this->storeManager()))->execute();
    }

    public function testDisconnectMarksOnlyThisStoreAsDisconnected(): void
    {
        $helper = $this->helperExpectingActive('0');

        (new Disconnect($this->context(), $helper, $this->storeManager()))->execute();
    }

    private function helperExpectingActive(string $value): Data
    {
        $helper = $this->createMock(Data::class);
        $helper->method('getConfig')->willReturn('configured');
        $helper->method('encrypt')->willReturn('sj#encrypted');
        $helper->expects($this->once())->method('setConfig')->with('active', $value, '3');

        return $helper;
    }

    private function context(): Context
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnMap([['store', null, '3']]);

        $redirectFactory = $this->createStub(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($this->createStub(Redirect::class));

        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultRedirectFactory')->willReturn($redirectFactory);
        $context->method('getMessageManager')->willReturn($this->createStub(ManagerInterface::class));

        return $context;
    }

    private function storeManager(): StoreManagerInterface
    {
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturn('https://shop.test/');
        $store->method('getUrl')->willReturn('https://shop.test/admin/');

        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        return $storeManager;
    }
}
