<?php
namespace SmartCustomer\Reviews\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class MaxNoTags implements OptionSourceInterface
{

    /**
     * Return array of options as value-label pairs, eg. value => label
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => '9999',
                'label' => __('All')
            ],
            [
                'value' => '6',
                'label' => __('6')
            ],
            [
                'value' => '5',
                'label' => __('5')
            ],
            [
                'value' => '4',
                'label' => __('4')
            ],
            [
                'value' => '3',
                'label' => __('3')
            ],
            [
                'value' => '2',
                'label' => __('2')
            ],
            [
                'value' => '1',
                'label' => __('1')
            ],
            [
                'value' => '0',
                'label' => __('None')
            ],
        ];
    }
}
