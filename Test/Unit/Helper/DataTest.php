<?php

namespace SmartCustomer\Reviews\Test\Unit\Helper;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order;
use PHPUnit\Framework\TestCase;
use SmartCustomer\Reviews\Helper\Data;

class DataTest extends TestCase
{
    public function testLanguageUsesTheSelectedStoreLocale(): void
    {
        $config = $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $context = $this->createStub(Context::class);
        $context->method('getScopeConfig')->willReturn($config);
        $helper = new Data($context, $this->createStub(WriterInterface::class), $this->createStub(TypeListInterface::class));
        $cases = [
            'en_US' => 'en', 'ar_AE' => 'ar', 'nl_NL' => 'nl', 'fr_FR' => 'fr',
            'de_DE' => 'de', 'it_IT' => 'it', 'pl_PL' => 'pl', 'es_ES' => 'es', 'uk_UA' => 'uk',
            'ja_JP' => 'jp', 'zh_CN' => 'cn', 'zh_HK' => 'hk', 'zh_TW' => 'hk',
            'zh_Hant_CN' => 'hk', 'zh_Hans_HK' => 'cn', 'pt_BR' => 'en', '' => 'en',
        ];
        $locale = '';
        $config->expects($this->exactly(count($cases)))->method('getValue')
            ->with('general/locale/code', 'stores', 17)
            ->willReturnCallback(function () use (&$locale) { return $locale; });
        foreach ($cases as $locale => $language) {
            $this->assertSame($language, $helper->getLanguage(17), $locale);
        }
    }

    public function testOrderLocaleIsTheOneSavedWhenTheOrderWasPlaced(): void
    {
        $config = $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $config->expects($this->never())->method('getValue');
        $order = $this->createStub(Order::class);
        $order->method('getData')->willReturnCallback(
            static fn($key) => $key === 'smartcustomer_locale' ? 'ja_JP' : null
        );

        $this->assertSame('ja_JP', $this->helperReading($config)->getOrderLocale($order));
    }

    public function testOrderWithoutASavedLocaleUsesItsStoreLocale(): void
    {
        $config = $this->createMock(\Magento\Framework\App\Config\ScopeConfigInterface::class);
        $config->expects($this->once())->method('getValue')
            ->with('general/locale/code', 'stores', 17)
            ->willReturn('de_DE');
        $order = $this->createStub(Order::class);
        $order->method('getStoreId')->willReturn(17);

        $this->assertSame('de_DE', $this->helperReading($config)->getOrderLocale($order));
    }

    private function helperReading($config): Data
    {
        $context = $this->createStub(Context::class);
        $context->method('getScopeConfig')->willReturn($config);

        return new Data($context, $this->createStub(WriterInterface::class), $this->createStub(TypeListInterface::class));
    }

    public function testSetConfigCleansOnlyTheConfigAndBlockCaches(): void
    {
        $writer = $this->createMock(WriterInterface::class);
        $writer->expects($this->once())->method('save')->with('sitejabber/reviews/active', '1', 'default', 0);

        $cleaned = [];
        $typeList = $this->createStub(TypeListInterface::class);
        $typeList->method('cleanType')->willReturnCallback(function ($type) use (&$cleaned) {
            $cleaned[] = $type;
        });

        $helper = new Data($this->createStub(Context::class), $writer, $typeList);
        $helper->setConfig('active', '1');

        $this->assertSame(['config', 'block_html'], $cleaned);
    }
}
