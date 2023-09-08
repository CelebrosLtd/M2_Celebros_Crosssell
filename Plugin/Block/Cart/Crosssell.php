<?php
/**
 * Celebros (C) 2023. All Rights Reserved.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish correct extension functionality.
 * If you wish to customize it, please contact Celebros.
 */
namespace Celebros\Crosssell\Plugin\Block\Cart;

use Celebros\Crosssell\Helper\Api as Api;
use Magento\Checkout\Model\Session as Session;
use Magento\Catalog\Block\Product\Context as Context;
use Magento\Catalog\Model\ResourceModel\Product\Collection as Collection;
use Magento\Catalog\Model\ProductRepository;

/**
 * Crosssell block plugin
 */
class Crosssell
{
    /**
     * @var array
     */
    protected $items = [];

    /**
     * @var int
     */
    protected $maxItemCount;

    /**
     * @var array
     */
    protected $_addedIds = [];

    /**
     * @var \Celebros\Crosssell\Helper\Data
     */
    private $helper;

    /**
     * @var Session
     */
    private $checkoutSession;

    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var \Magento\Catalog\Model\Config
     */
    private $catalogConfig;


    /**
     * @param \Celebros\Crosssell\Helper\Data $helper
     * @return void
     */
    public function __construct(
        Api $helper,
        Session $checkoutSession,
        ProductRepository $productRepository,
        \Magento\Catalog\Model\Config $catalogConfig
    ) {
        $this->helper = $helper;
        $this->checkoutSession = $checkoutSession;
        $this->productRepository = $productRepository;
        $this->catalogConfig = $catalogConfig;
        $this->maxItemCount = $this->helper->getCrosssellLimit();
    }

    /**
     * @param \Magento\Catalog\Model\Product $product
     * @return void
     */
    protected function collectItems(\Magento\Catalog\Model\Product $product)
    {
        $id = $product->getEntityId();
        $product = $this->productRepository->getById($id);

        $collection = $product->getCrossSellProductCollection();
        $collection = $this->addProductAttributesAndPrices($collection);
        foreach ($collection as $it) {
            if (!in_array($it->getEntityId(), $this->_addedIds)
            && count($this->items) < $this->maxItemCount) {
                $this->items[] = $it;
                $this->_addedIds[] = $it->getEntityId();
            }
        }
    }

    /**
     * @return int
     */
    protected function getLastAddedProductId()
    {
        return $this->checkoutSession->getLastAddedProductId(true);
    }

    /**
     * @param \Magento\Catalog\Model\ResourceModel\Product\Collection $collection
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    protected function addProductAttributesAndPrices(Collection $collection)
    {
        return $collection
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addAttributeToSelect(
                $this->catalogConfig->getProductAttributes()
            )->addUrlRewrite();
    }

    /**
     * @param \Magento\Checkout\Block\Cart\Crosssell $subj
     * @param callable $proceed
     * @return array
     */
    public function aroundGetItems(\Magento\Checkout\Block\Cart\Crosssell $subj, callable $proceed)
    {
        if ($this->helper->isCrosssellEnabled()) {
            $this->items = (array)$subj->getData('items');
            if (empty($this->items)) {
                $lastAddedId = (int)$this->getLastAddedProductId();
                $lastAddedProduct = null;
                $quoteItems = $subj->getQuote()->getAllItems();
                foreach ($quoteItems as $item) {
                    $this->_addedIds[] = $item->getProductId();
                    if ($item->getProductId() == $lastAddedId) {
                        $lastAddedProduct = $item->getProduct();
                    }
                }

                if ($lastAddedProduct instanceof \Magento\Catalog\Model\Product) {
                    $this->collectItems($lastAddedProduct);
                }

                if (count($this->items) < $this->maxItemCount) {
                    foreach ($quoteItems as $item) {
                        $product = $item->getProduct();
                        $this->collectItems($product);
                    }
                }

                $subj->setData('items', $this->items);
            }

            return $this->items;
        }

        return $proceed();
    }

    /**
     * Retrieve array of cross-sell products
     *
     * @return array
     */
    public function aroundGetItemCollection(\Magento\TargetRule\Block\Checkout\Cart\Crosssell $subj, callable $proceed)
    {
        if ($this->helper->isCrosssellEnabled()) {
            $this->items = (array)$subj->getData('items');
            if (empty($this->items)) {
                $lastAddedId = (int)$this->getLastAddedProductId();
                $lastAddedProduct = null;
                $quoteItems = $subj->getQuote()->getAllItems();
                foreach ($quoteItems as $item) {
                    $this->_addedIds[] = $item->getProductId();
                    if ($item->getProductId() == $lastAddedId) {
                        $lastAddedProduct = $item->getProduct();
                    }
                }

                if ($lastAddedProduct instanceof \Magento\Catalog\Model\Product) {
                    $this->collectItems($lastAddedProduct);
                }

                if (count($this->items) < $this->maxItemCount) {
                    foreach ($quoteItems as $item) {
                        $product = $item->getProduct();
                        $this->collectItems($product);
                    }
                }
                $subj->setData('items', $this->items);
            }

            return $this->items;
        }

        return $proceed();
    }
}
