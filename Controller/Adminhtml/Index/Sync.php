<?php
namespace SmartCustomer\Reviews\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use SmartCustomer\Reviews\Helper\Data;
use SmartCustomer\Reviews\Helper\SyncOrders;

class Sync extends Action
{
    protected $_dataHelper;
    protected $_messageManager;
    protected $_sync;

    public function __construct(
        Context $context,
        Data $helper,
        SyncOrders $sync
    ) {
        parent::__construct($context);
        $this->_dataHelper = $helper;
        $this->_messageManager = $context->getMessageManager();
        $this->_sync = $sync;
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
                throw new \Exception('Some configuration is missing. Please complete the required fields.');
            }
            
            $get = $this->getRequest()->getQueryValue();
                
            // Sync past customers
            if (empty($get['from'])) {
                throw new \Exception('Your changes could not be submitted. Please try again.');
            }
            
            $from = $this->_dataHelper->decrypt($get['from'], $storeId);
            if (empty($from)) {
                throw new \Exception('Your changes could not be submitted. Please try again.');
            }
            
            $this->_sync->syncOrders([
                'api_key'        => $apiKey,
                'api_secret'    => urlencode($this->_dataHelper->encrypt($apiSecret, $storeId)),
                'from'            => $from
            ]);
            $resultRedirect->setPath('smartcustomer_reviews/index/index/store/' . $storeId);
        } catch (\Exception $e) {
            $this->_messageManager->addWarningMessage($e->getMessage());
            $resultRedirect->setPath('adminhtml/system_config/edit/section/smartcustomer');
        }
        
        return $resultRedirect;
    }
}
