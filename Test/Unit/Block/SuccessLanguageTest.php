<?php

namespace SmartCustomer\Reviews\Test\Unit\Block;

use PHPUnit\Framework\TestCase;
use SmartCustomer\Reviews\Block\Success;
use SmartCustomer\Reviews\Helper\Data;

class SuccessLanguageTest extends TestCase
{
    public function testCheckoutUsesTheOrderStoreLanguage(): void
    {
        $helper = $this->createMock(Data::class);
        $helper->expects($this->once())->method('getLanguage')->with(17)->willReturn('jp');
        $block = new class($helper) extends Success {
            public function __construct($helper)
            {
                $this->_dataHelper = $helper;
                $this->_order = new class { public function getStoreId() { return 17; } };
            }
        };
        $this->assertSame('jp', $block->getLanguage());
    }
}
