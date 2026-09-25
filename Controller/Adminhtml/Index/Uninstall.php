<?php
namespace SmartCustomer\Reviews\Controller\Adminhtml\Index;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use SmartCustomer\Reviews\Helper\Data;

class Uninstall extends Action
{
    protected $_helper;

    public function __construct(
        Context $context,
        Data $helper
    ) {
        parent::__construct($context);
        $this->_helper = $helper;
    }

    public function execute()
    {
        // Unset access token
        $this->_helper->setConfig('access_token', '');
        $this->_helper->setConfig('app_key', '');
        $this->_helper->setConfig('active', '');
        
        // Redirect to main controller
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('smartcustomer_reviews/index/index');
        
        return $resultRedirect;
    }
}
