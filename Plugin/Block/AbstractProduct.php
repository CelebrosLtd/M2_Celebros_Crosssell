<?php
/**
 * Celebros (C) 2023. All Rights Reserved.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish correct extension functionality.
 * If you wish to customize it, please contact Celebros.
 */
namespace Celebros\Crosssell\Plugin\Block;

use Magento\TargetRule\Model\Rule;

class AbstractProduct
{
    /**
     * @param int $type
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return int
     */
    public function afterGetPositionBehavior(\Magento\Catalog\Block\Product\AbstractProduct $subj, $result)
    {
        return Rule::SELECTED_ONLY;
        return $result;
    }
}
