<?php
namespace SmartCustomer\Reviews\Block\Widget;

use SmartCustomer\Reviews\Block\Widget\Base;
 
class ProductRating extends Base
{
    protected $_template = 'widget/product_rating.phtml';

    public function getAlign()
    {
        return $this->hasData('horizontal_align') ? $this->getData('horizontal_align') : 'center';
    }

    public function isProductRatingEnabled()
    {
        $storeId = $this->_getStoreId();
        return $this->_sjHelper->getConfig('product_rating_enabled', $storeId, 'sitejabber_widgets/product_rating/');
    }
}
