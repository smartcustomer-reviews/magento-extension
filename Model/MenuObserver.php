<?php
namespace SmartCustomer\Reviews\Model;

use Magento\Backend\Block\Menu;
use Magento\Backend\Model\Menu\Item\Factory;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use SmartCustomer\Reviews\Helper\Data;

class MenuObserver implements ObserverInterface
{

    protected $_dataHelper;
    protected $_menuItemFactory;
    protected $_storeManager;

    public function __construct(
        Data $helper,
        Factory $menuItemFactory,
        StoreManagerInterface $storeManager
    ) {
        $this->_dataHelper = $helper;
        $this->_menuItemFactory = $menuItemFactory;
        $this->_storeManager = $storeManager;
    }

    public function execute(Observer $observer)
    {
        $block = $observer->getBlock();

        if ($block instanceof Menu) {
            $menuModel = $block->getMenuModel();
            
            $domains = [];
            $websites = $this->_storeManager->getWebsites();
            foreach ($websites as $website) {
                $stores = $website->getStores();
                foreach ($stores as $store) {
                    $storeId = $store->getId();
                    $storeName = $store->getName();
                    $storeSlug = $store->getCode();
                    $domain = preg_replace('#https?://#', '', $store->getBaseUrl());
                    
                    // Only show a subsection by domain
                    if (in_array($domain, $domains)) {
                        continue;
                    }
                    
                    $domains[] = $domain;
                    
                    $item = $this->_menuItemFactory->create([
                        'id'        => 'SmartCustomer_Reviews::' . $storeSlug,
                        'resource'    => 'SmartCustomer_Reviews::' . $storeSlug,
                        'title'        => $storeName
                    ]);
                    $menuModel->add($item, 'SmartCustomer_Reviews::index');
                    
                    // Store configuration link
                    $item = $this->_menuItemFactory->create([
                        'action'    => 'adminhtml/system_config/edit/section/smartcustomer/store/' . $storeId,
                        'id'        => 'SmartCustomer_Reviews::conf_' . $storeId,
                        'resource'    => 'SmartCustomer_Reviews::conf_' . $storeId,
                        'title'        => 'Configuration'
                    ]);
                    $menuModel->add($item, 'SmartCustomer_Reviews::' . $storeSlug, 99);
                    
                    $enabled = $this->_dataHelper->getConfig('enabled', $storeId);
                    $active = $this->_dataHelper->getConfig('active', $storeId);

                    $item = $this->_menuItemFactory->create([
                        'action'    => 'smartcustomer_reviews/index/index/store/' . $storeId,
                        'id'        => 'SmartCustomer_Reviews::access_' . $storeId,
                        'resource'    => 'SmartCustomer_Reviews::access_' . $storeId,
                        'title'        => $enabled && $active ? 'Access your SmartCustomer dashboard' : 'Connect to your SmartCustomer account'
                    ]);
                    $menuModel->add($item, 'SmartCustomer_Reviews::' . $storeSlug, 99);

                    if ($enabled && $active) {
                        $item = $this->_menuItemFactory->create([
                            'action'    => 'smartcustomer_reviews/index/disconnect/store/' . $storeId,
                            'id'        => 'SmartCustomer_Reviews::disconnect_' . $storeId,
                            'resource'    => 'SmartCustomer_Reviews::disconnect_' . $storeId,
                            'title'        => 'Disconnect from your SmartCustomer account'
                        ]);
                        $menuModel->add($item, 'SmartCustomer_Reviews::' . $storeSlug, 99);
                    }
                }
            }
        }
    }
}
