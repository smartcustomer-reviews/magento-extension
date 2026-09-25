<?php
namespace SmartCustomer\Reviews\Block\Widget;

use SmartCustomer\Reviews\Block\Widget\Base;
 
class ProductReviews extends Base
{
    protected $_template = 'widget/product_reviews.phtml';

    public function isProductReviewsEnabled()
    {
        $storeId = $this->_getStoreId();
        return $this->_sjHelper->getConfig('product_reviews_enabled', $storeId, 'sitejabber_widgets/product_reviews/');
    }

    public function printJson()
    {
        $this->getProduct();
        
        $product = [
            'id'            => $this->_product->getId(),
            'title'            => $this->_product->getName(),
            'description'    => $this->_product->getDescription(),
            'sku'            => $this->_product->getSku(),
            'price'            => $this->_product->getPrice(),
            'product_link'    => $this->_product->getProductUrl()
        ];

        $itemGroup = $this->_product->getItemGroup();
        if (!empty($itemGroup)) {
            $product['item_group'] = $itemGroup;
        }

        $brandKey = $this->hasData('brand_attr_code') ? $this->getData('brand_attr_code') : 'manufacturer';
        $brand = $this->_product->getAttributeText($brandKey);
        if (is_string($brand) && $brand !== '') {
            $product['vendor'] = $brand;
        }

        $storeId = $this->_getStoreId();
        $maxCategories = (int) $this->_sjHelper->getConfig('instant_feedback_max_tags', $storeId, 'sitejabber_widgets/instant_feedback/');
        if ($maxCategories) {
            $categoryIds = $this->_product->getCategoryIds();
            if (!empty($categoryIds)) {
                $tags = [];
                foreach ($categoryIds as $categoryId) {
                    $category = $this->_objectManager->create('Magento\Catalog\Model\Category')->load($categoryId);
                    $tags[] = $category->getName();
                }

                $product['tags'] = array_slice($tags, 0, $maxCategories);
            }
        }
        
        $media = $this->_product->getMediaGalleryImages();
        if (!empty($media)) {
            $images = [];
            foreach ($media as $m) {
                $images[] = $m->getData('url');
            }

            $product['images'] = $images;
        }

        if ($this->_product->getTypeId() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
            $childrens = $this->_product->getTypeInstance()->getUsedProducts($this->_product);
            if (!empty($childrens)) {
                $variants = [];
                foreach ($childrens as $children) {
                    $variant = [
                        'id'    => $children->getId(),
                        'title'    => $children->getName(),
                        'sku'    => $children->getSku(),
                        'price'    => $children->getPrice()
                    ];
                    $media = $children->getMediaGalleryImages();
                    if (!empty($media)) {
                        $images = [];
                        foreach ($media as $m) {
                            $images[] = $m->getData('url');
                        }

                        $variant['images'] = $images;
                    }

                    $variants[] = $variant;
                }

                $product['variants'] = $variants;
            }
        }

        return json_encode($product);
    }
}
