<?php
namespace SmartCustomer\Reviews\Model;

use Magento\Framework\App\Cache\Frontend\Pool;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ConfigObserver implements ObserverInterface
{
    protected $_cacheFrontendPool;
    protected $_cacheTypeList;
    
    public function __construct(
        Pool $cacheFrontendPool,
        TypeListInterface $cacheTypeList
    ) {
        $this->_cacheFrontendPool = $cacheFrontendPool;
        $this->_cacheTypeList = $cacheTypeList;
    }

    public function execute(Observer $observer)
    {
        $this->_cacheTypeList->cleanType('block_html');
        foreach ($this->_cacheFrontendPool as $cacheFrontend) {
            $cacheFrontend->getBackend()->clean();
        }
    }
}
