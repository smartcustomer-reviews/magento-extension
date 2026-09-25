<?php
namespace SmartCustomer\Reviews\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use SmartCustomer\Reviews\Helper\Data;

class Setup extends Action
{
    protected $_dataHelper;
    protected $_messageManager;

    public function __construct(
        Context $context,
        Data $helper
    ) {
        parent::__construct($context);
        $this->_dataHelper = $helper;
        $this->_messageManager = $context->getMessageManager();
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
            
            // Store access token and redirect back to SmartCustomer
            if (!empty($get['error'])) {
                throw new \Exception($get['error']);
            }
            
            $redirectUrl = isset($get['redirect']) ? $this->_dataHelper->decrypt($get['redirect'], $storeId) : null;
            if (empty($redirectUrl)) {
                throw new \Exception('Your changes could not be submitted. Please try again.');
            }

            $resultRedirect->setUrl($redirectUrl);
        } catch (\Exception $e) {
            $this->_messageManager->addWarningMessage($e->getMessage());
            $resultRedirect->setPath('adminhtml/system_config/edit/section/smartcustomer');
        }
        
        return $resultRedirect;
    }
}
