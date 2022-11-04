<?php

/**
 * Celebros (C) 2022. All Rights Reserved.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish correct extension functionality.
 * If you wish to customize it, please contact Celebros.
 */

namespace Celebros\Crosssell\Plugin\Model;

use Celebros\Crosssell\Helper\Api as Api;

class Product
{
    /**
     * @var \Celebros\Crosssell\Helper\Data
     */
    public $helper;

    /**
     * @param \Celebros\Crosssell\Helper\Data $helper
     * @return void
     */
    public function __construct(
        Api $helper
    ) {
        $this->helper = $helper;
    }

    /**
     * @param \Magento\Catalog\Model\ResourceModel\Product\Link\Product\Collection $collection
     * @return \Magento\Catalog\Model\ResourceModel\Product\Link\Product\Collection
     */
    protected function prepareAndSortCollection(
        \Magento\Catalog\Model\ResourceModel\Product\Link\Product\Collection $collection,
        array $skus
    ) {
        $collection->addFieldToFilter('sku', $skus);
        $collection->getSelect()->order(new \Zend_Db_Expr("FIELD(`e`.`sku`, '" . implode("','", $skus) . "') ASC"));

        return $collection;
    }

    /**
     * @param \Magento\Catalog\Model\Product $subj
     * @param \Magento\Catalog\Model\ResourceModel\Product\Link\Product\Collection $result
     * @return \Magento\Catalog\Model\ResourceModel\Product\Link\Product\Collection
     */
    public function afterGetUpSellProductCollection(\Magento\Catalog\Model\Product $subj, $result)
    {
        if ($this->helper->isUpsellEnabled()) {
            $type = 'upsell';
            $skus = $this->helper->getRecommendedIds(
                $subj->getSku(),
                $this->helper->getUpsellLimit(),
                $type
            );

            if (!empty($skus)) {
                $collection = $subj->getLinkInstance()->useUpSellLinks()->getProductCollection();
                return $this->prepareAndSortCollection($collection, $skus);
            }
        }

        return $result;
    }

    /**
     * @param \Magento\Catalog\Model\Product $subj
     * @param \Magento\Catalog\Model\ResourceModel\Product\Link\Product\Collection $result
     * @return \Magento\Catalog\Model\ResourceModel\Product\Link\Product\Collection
     */
    public function afterGetCrossSellProductCollection(\Magento\Catalog\Model\Product $subj, $result)
    {
        if ($this->helper->isCrosssellEnabled()) {
            $type = 'crosssell';
            $skus = $this->helper->getRecommendedIds(
                $subj->getSku(),
                $this->helper->getCrosssellLimit(),
                $type
            );

            if (!empty($skus)) {
                $collection = $subj->getLinkInstance()->useCrossSellLinks()->getProductCollection();
                return $this->prepareAndSortCollection($collection, $skus);
            }
        }

        return $result;
    }
}
