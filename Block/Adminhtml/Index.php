<?php
namespace SmartCustomer\Reviews\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use SmartCustomer\Reviews\Helper\Data;
use SmartCustomer\Reviews\Model\Config;

class Index extends Template
{
    protected $_helper;

    public function __construct(
        Context $context,
        Data $helper
    ) {
        parent::__construct($context);
        
        $this->_helper = $helper;
    }
    
    /**
     * Get store data
     *
     * @return string
     */
    public function canInstallApp()
    {
        $email = $this->_scopeConfig->getValue(
            'trans_email/ident_general/email',
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'The general contact email is empty or invalid. <a href="' . $this->getUrl('admin/system_config/edit/section/trans_email') . '">Click here</a> to fix it';
        }
        
        $name = $this->_scopeConfig->getValue(
            'trans_email/ident_general/name',
            ScopeConfigInterface::SCOPE_TYPE_DEFAULT
        );
        
        if (empty($name) || !preg_match('#^[a-z-]+(\s[a-z-]+)+$#i', $name)) {
            return 'The general contact name is empty or invalid. <a href="' . $this->getUrl('admin/system_config/edit/section/trans_email') . '">Click here</a> to fix it';
        }
        
        return true;
    }
    
    public function getAccessToken()
    {
        return $this->_helper->getConfig('access_token');
    }
    
    /**
     * Get path for installation callback
     *
     * @return string
     */
    public function getEndpointUrl()
    {
        return Config::ENDPOINT_URL;
    }
    
    /**
     * Get path for installation callback
     *
     * @return string
     */
    public function getInstallCallbackUrl()
    {
        return str_replace($this->getBaseUrl(), '', $this->getUrl('smartcustomer_reviews/index/setup'));
    }
    
    /**
     * Get path for installation callback
     *
     * @return string
     */
    public function getSyncCallbackUrl()
    {
        return str_replace($this->getBaseUrl(), '', $this->getUrl('smartcustomer_reviews/index/sync'));
    }
    
    /**
     * Get path for uninstallation callback
     *
     * @return string
     */
    public function getUninstallCallbackUrl()
    {
        return str_replace($this->getBaseUrl(), '', $this->getUrl('smartcustomer_reviews/index/uninstall'));
    }
    
    /**
     * Get store data
     *
     * @return string
     */
    public function getStoreData()
    {
        return [
            'email'    => $this->_scopeConfig->getValue(
                'trans_email/ident_general/email',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            ),
            'name'    => $this->_scopeConfig->getValue(
                'trans_email/ident_general/name',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE
            )
        ];
    }
}
