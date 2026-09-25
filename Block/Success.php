<?php
namespace SmartCustomer\Reviews\Block;

use Magento\Checkout\Model\Session;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use SmartCustomer\Reviews\Helper\Data;
use SmartCustomer\Reviews\Model\Config;

class Success extends Template
{
    protected $_checkoutSession;
    protected $_dataHelper;
    protected $_objectManager;
    protected $_order;
    protected $_orderFactory;
    protected $_storeManager;

    public function __construct(
        Context $context,
        Data $helper,
        OrderFactory $orderFactory,
        Session $checkoutSession,
        array $data = []
    ) {
        parent::__construct($context, $data);
        
        $this->_checkoutSession = $checkoutSession;
        $this->_dataHelper = $helper;
        $this->_objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $this->_orderFactory = $orderFactory;
        $this->_storeManager = $context->getStoreManager();
    }

    public function getApiKey()
    {
        $storeId = $this->_storeManager->getStore()->getId();
        return $this->_dataHelper->getConfig('api_key', $storeId);
    }
    
    public function getBaseDomain()
    {
        $domain = preg_replace('#https?://#', '', $this->_storeManager->getStore()->getBaseUrl());
        return trim($domain, '/');
    }
    
    public function getCustomerEmail()
    {
        $this->_getOrder();
        
        $email = $this->_order->getCustomerEmail();
        if (empty($email) && $this->_order->getShippingAddress()) {
            $email = $this->_order->getShippingAddress()->getEmail();
        }
        if (empty($email) && $this->_order->getBillingAddress()) {
            $email = $this->_order->getBillingAddress()->getEmail();
        }
        
        return $email;
    }
    
    public function getCustomerFirstName($field = null)
    {
        $this->_getOrder();
        
        $firstName = trim($this->_order->getCustomerFirstName());
        if (empty($firstName) && $this->_order->getShippingAddress()) {
            $firstName = trim($this->_order->getShippingAddress()->getFirstname());
        }
        if (empty($firstName) && $this->_order->getBillingAddress()) {
            $firstName = trim($this->_order->getBillingAddress()->getFirstname());
        }
        
        return $firstName;
    }
    
    public function getCustomerLastName($field = null)
    {
        $this->_getOrder();
        
        $lastName = trim($this->_order->getCustomerLastName());
        if (empty($lastName) && $this->_order->getShippingAddress()) {
            $lastName = trim($this->_order->getShippingAddress()->getLastname());
        }
        if (empty($lastName) && $this->_order->getBillingAddress()) {
            $lastName = trim($this->_order->getBillingAddress()->getLastname());
        }
        
        return $lastName;
    }

    public function getDomainUrl()
    {
        return Config::DOMAIN_URL;
    }
    
    public function getWidgetDomain()
    {
        return Config::WIDGET_DOMAIN;
    }

    public function getOrderCurrencyCode()
    {
        $this->_getOrder();
        
        return $this->_order->getOrderCurrencyCode();
    }
    
    public function getOrderId()
    {
        $this->_getOrder();
        
        return $this->_order->getRealOrderId();
    }

    public function getOrderSubtotal()
    {
        $this->_getOrder();
        
        return $this->_order->getBaseSubtotal();
    }

    public function getOrderTaxes()
    {
        $this->_getOrder();
        
        return $this->_order->getTaxAmount();
    }

    public function getOrderTotal()
    {
        $this->_getOrder();
        
        return $this->_order->getBaseGrandTotal();
    }

    public function getProducts()
    {
        $this->_getOrder();

        $products = [];
        $items = $this->_order->getAllVisibleItems();
        foreach ($items as $product) {
            $products[] = $product;
        }

        return $products;
    }

    public function getProductCategories($p)
    {
        $product = $this->_objectManager->get('Magento\Catalog\Model\Product')->load($p->getProductId());
        $categoryIds = $product->getCategoryIds();
        $categoryNames = [];
        foreach ($categoryIds as $categoryId) {
            $category = $this->_objectManager->create('Magento\Catalog\Model\Category')->load($categoryId);
            $categoryNames[] = $category->getName();
        }

        return implode(',', $categoryNames);
    }

    public function getProductImage($p)
    {
        $product = $this->_objectManager->get('Magento\Catalog\Model\Product')->load($p->getProductId());
        $imageHelper  = $this->_objectManager->get('\Magento\Catalog\Helper\Image');
        return $imageHelper->init($product, 'product_page_main_image')->setImageFile($product->getFile())->getUrl();
    }

    public function getProductUrl($p)
    {
        $product = $this->_objectManager->get('Magento\Catalog\Model\Product')->load($p->getProductId());
        return $product->getProductUrl();
    }

    public function isIstantFeedbackEnabled()
    {
        $storeId = $this->_storeManager->getStore()->getId();
        return $this->_dataHelper->getConfig('instant_feedback_enabled', $storeId, 'sitejabber_widgets/instant_feedback/');
    }

    public function getItemGroup($p)
    {
        $product = $this->_objectManager->create('Magento\Catalog\Model\Product')->load($p->getProductId());

        $itemGroup = $product->getCustomAttribute('item_group')
            ? $product->getCustomAttribute('item_group')->getValue()
            : null;

        return $itemGroup;
    }

    private function _getOrder()
    {
        if (!empty($this->_order)) {
            return $this->_order;
        }
        
        try {
            $incrementId = $this->_checkoutSession->getLastRealOrder()->getIncrementId();
            $this->_order = $this->_orderFactory->create()->loadByIncrementId($incrementId);
        } catch (\Exception $e) {

        }
    }
}
