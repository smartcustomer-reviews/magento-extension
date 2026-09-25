<?php

namespace SmartCustomer\Reviews\Test\Unit\Helper;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\Context;
use PHPUnit\Framework\TestCase;
use SmartCustomer\Reviews\Helper\Data;

class DataTest extends TestCase
{
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
