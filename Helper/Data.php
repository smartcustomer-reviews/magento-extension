<?php

namespace SmartCustomer\Reviews\Helper;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    const SMARTCUSTOMER_SETTINGS = 'sitejabber/reviews/';

    protected $_configWriter;
    protected $_cacheTypeList;

    public function __construct(
        Context $context,
        WriterInterface $configWriter,
        TypeListInterface $cacheTypeList
    ) {
        parent::__construct($context);
        
        $this->_configWriter = $configWriter;
        $this->_cacheTypeList = $cacheTypeList;
    }

    public function getConfig($config, $storeId = null, $path = null)
    {
        $path = (empty($path) ? self::SMARTCUSTOMER_SETTINGS : $path) . $config;
        
        if (empty($storeId) || !is_numeric($storeId)) {
            $setting = $this->scopeConfig->getValue($path, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0);
        } else {
            $setting = $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORES, $storeId);
        }
        
        return $setting ? $setting : null;
    }

    public function setConfig($config, $value, $storeId = null, $path = null)
    {
        $path = (empty($path) ? self::SMARTCUSTOMER_SETTINGS : $path) . $config;
        
        if (empty($storeId) || !is_numeric($storeId)) {
            $this->_configWriter->save($path, $value, ScopeConfigInterface::SCOPE_TYPE_DEFAULT, 0);
        } else {
            $this->_configWriter->save($path, $value, ScopeInterface::SCOPE_STORES, $storeId);
        }

        $this->_cacheTypeList->cleanType('config');
        $this->_cacheTypeList->cleanType('block_html');
    }
    
    public function encrypt($string, $storeId = null)
    {
        $password = $this->getConfig('api_secret', $storeId);
        
        $method = "AES-256-CBC";
        $key = hash('sha256', $password, true);
        $iv = openssl_random_pseudo_bytes(16);
        
        $ciphertext = openssl_encrypt($string, $method, $key, OPENSSL_RAW_DATA, $iv);
        $hash = hash_hmac('sha256', $ciphertext, $key, true);
        
        return 'sj#' . base64_encode($iv . $hash . $ciphertext);
    }

    public function decrypt($string, $storeId = null)
    {
        $password = $this->getConfig('api_secret', $storeId);
        
        $encodingCheck = substr($string, 0, 3) == 'sj#' ? false : true;
        $string = substr($encodingCheck ? urldecode($string) : $string, 3);
        
        $string = base64_decode($string);
        
        $method = "AES-256-CBC";
        $iv = substr($string, 0, 16);
        $hash = substr($string, 16, 32);
        $ciphertext = substr($string, 48);
        $key = hash('sha256', $password, true);
        
        if (!hash_equals(hash_hmac('sha256', $ciphertext, $key, true), $hash)) {
            return null;
        }
        
        return openssl_decrypt($ciphertext, $method, $key, OPENSSL_RAW_DATA, $iv);
    }
}
