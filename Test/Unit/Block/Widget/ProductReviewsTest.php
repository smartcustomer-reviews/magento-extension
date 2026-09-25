<?php

namespace SmartCustomer\Reviews\Test\Unit\Block\Widget;

use Magento\Catalog\Helper\Data as CatalogData;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SmartCustomer\Reviews\Block\Widget\ProductReviews;
use SmartCustomer\Reviews\Helper\Data;

class ProductReviewsTest extends TestCase
{
    #[DataProvider('brands')]
    public function testVendorIsSentOnlyWhenTheProductHasABrand($brand, ?string $vendor): void
    {
        // Base block reads the global object manager in its constructor
        ObjectManager::setInstance($this->createStub(ObjectManagerInterface::class));

        $product = $this->createStub(Product::class);
        $product->method('getAttributeText')->willReturn($brand);
        $product->method('getTypeId')->willReturn('simple');
        $product->method('getMediaGalleryImages')->willReturn(null);
        $catalogHelper = $this->createStub(CatalogData::class);
        $catalogHelper->method('getProduct')->willReturn($product);

        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $context = $this->createStub(Context::class);
        $context->method('getStoreManager')->willReturn($storeManager);

        $block = new ProductReviews($context, $catalogHelper, $this->createStub(Data::class));
        $json = json_decode($block->printJson(), true);

        $this->assertSame($vendor, $json['vendor'] ?? null);
    }

    public static function brands(): array
    {
        return [
            'brand' => ['Acme Tools', 'Acme Tools'],
            'no brand' => [false, null],
            'multi-select brand' => [['Acme', 'Tools'], null],
        ];
    }
}
