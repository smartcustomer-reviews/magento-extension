<?php
namespace SmartCustomer\Reviews\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Store\Model\StoreManagerInterface;
use SmartCustomer\Reviews\Helper\Data;
use SmartCustomer\Reviews\Model\Config;

class Index extends Action
{
    protected $_dataHelper;
    protected $_messageManager;
    protected $_storeManager;
    
    public function __construct(
        Context $context,
        Data $helper,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->_dataHelper = $helper;
        $this->_messageManager = $context->getMessageManager();
        $this->_storeManager = $storeManager;
    }

    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        
        try {
            $storeId = $this->getRequest()->getParam('store');
            
            if (empty($storeId)) {
                throw new \Exception('Your changes could not be submitted. Please try again.');
            }
            
            $apiKey = $this->_dataHelper->getConfig('api_key', $storeId);
            $apiSecret = $this->_dataHelper->getConfig('api_secret', $storeId);
            $enabled = $this->_dataHelper->getConfig('enabled', $storeId);
            
            if (empty($apiKey) || empty($apiSecret) || empty($enabled)) {
                throw new \Exception('Your changes could not be submitted. Please try again.');
            }

            $this->_dataHelper->setConfig('active', '1', $storeId);
            $redirectUrl = str_replace($this->_storeManager->getStore()->getBaseUrl(), '', $this->_storeManager->getStore()->getUrl('smartcustomer_reviews/index/setup/store/' . $storeId));
            $syncUrl = str_replace($this->_storeManager->getStore()->getBaseUrl(), '', $this->_storeManager->getStore()->getUrl('smartcustomer_reviews/index/sync/store/' . $storeId));
            $url = Config::ENDPOINT_URL . '/access?api_key=' . urlencode($apiKey) . '&api_secret=' . urlencode($this->_dataHelper->encrypt($apiSecret, $storeId)) . '&redirect=' . urlencode($this->_dataHelper->encrypt($redirectUrl, $storeId)) . '&sync=' . urlencode($this->_dataHelper->encrypt($syncUrl, $storeId));
                
            $resultRedirect->setUrl($url);
        } catch (\Exception $e) {
            $this->_messageManager->addWarningMessage($e->getMessage());
            $resultRedirect->setPath('adminhtml/system_config/edit/section/smartcustomer');
        }
        
        return $resultRedirect;
    }
}
