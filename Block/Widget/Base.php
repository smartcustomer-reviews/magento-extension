<?php
namespace SmartCustomer\Reviews\Block\Widget;

use Magento\Catalog\Helper\Data;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;
use SmartCustomer\Reviews\Model\Config;
use SmartCustomer\Reviews\Helper\Data as SjData;
 
class Base extends Template implements BlockInterface
{
    protected $_dataHelper;
    protected $_objectManager;
    protected $_product;
    protected $_sjHelper;
    protected $_storeManager;

    protected $_storeId;

    public function __construct(
        Context $context,
        Data $helper,
        SjData $sjHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        
        $this->_dataHelper = $helper;
        $this->_objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->_sjHelper = $sjHelper;
        $this->_storeManager = $context->getStoreManager();
    }

    public function getApiKey()
    {
        $storeId = $this->_getStoreId();
        return $this->_sjHelper->getConfig('api_key', $storeId);
    }

    public function getDomainUrl()
    {
        return Config::WIDGET_DOMAIN;
    }
    
    public function getWidgetDomain()
    {
        return Config::WIDGET_DOMAIN;
    }

    public function getProduct()
    {
        if (is_null($this->_product)) {
            $this->_product = $this->_dataHelper->getProduct();
        }
        
        return $this->_product;
    }

    public function getProductId()
    {
        $this->getProduct();

        if ($this->_product->getTypeId() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
            $childrens = $this->_product->getTypeInstance()->getUsedProducts($this->_product);
            if (!empty($childrens)) {
                foreach ($childrens as $children) {
                    return $children->getSku() ?? $children->getId();
                }
            }
        }

        return $this->_product->getSku() ?? $this->_product->getId();
    }
    
    public function getProductGroup()
    {
        $this->getProduct();

        if ($this->_product->getTypeId() == \Magento\ConfigurableProduct\Model\Product\Type\Configurable::TYPE_CODE) {
            return $this->_product->getSku() ?? $this->_product->getId();
        }

        return '';
    }

    public function getItemGroup()
    {
        $this->getProduct();

        return $this->_product->getAttribute('item_group');
    }

    protected function _getStoreId()
    {
        if (is_null($this->_storeId)) {
            $this->_storeId = $this->_storeManager->getStore()->getId();
        }

        return $this->_storeId;
    }
}
