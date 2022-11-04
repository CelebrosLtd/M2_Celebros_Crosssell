<?php

/**
 * Celebros (C) 2022. All Rights Reserved.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish correct extension functionality.
 * If you wish to customize it, please contact Celebros.
 */

namespace Celebros\Crosssell\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Zend\Uri\UriFactory as UriFactory;

/**
 * Crosssell API helper
 */
class Api extends \Celebros\Crosssell\Helper\Data
{
    public const XML_PATH_ADVANCED = 'celebros_crosssell/advanced/';
    public const XML_PATH_HOST_PARAM = 'crosssell_address';
    public const API_URL_PATH = '/JsonEndPoint/ProductsRecommendation.aspx';
    public const API_SUCCESS_STATUS = 'Success';

    /**
     * @var array
     */
    protected $apiQuery = [];

    /**
     * @var string
     */
    protected $apiUrl;

    /**
     * @var array
     */
    protected $response = [];

    /**
     * @var array
     */
    protected $requestParams = [
        'siteKey' => 'crosssell_customer_name',
        'RequestHandle' => 'crosssell_request_handle',
        'RequestType' => '1',
        'Encoding' => 'utf-8'
    ];

    /**
     * @var \Magento\Framework\HTTP\Client\Curl
     */
    public $curl;

    /**
     * @var \Magento\Framework\Json\Helper\Data
     */
    public $jsonHelper;

    /**
     * @var \Celebros\Crosssell\Helper\Cache
     */
    public $cache;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    public $messageManager;

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Framework\HTTP\Client\Curl $curl
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param \Celebros\Crosssell\Helper\Cache $cache
     * @param \Magento\Framework\Message\ManagerInterface $messageManager
     * @return void
     */
    public function __construct(
        Context $context,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Celebros\Crosssell\Helper\Cache $cache,
        \Magento\Framework\Message\ManagerInterface $messageManager
    ) {
        $this->curl = $curl;
        $this->jsonHelper = $jsonHelper;
        $this->cache = $cache;
        $this->messageManager = $messageManager;
        parent::__construct($context);
    }

    /**
     * @param string $param
     * @param int $store
     * @return string
     */
    protected function extractParam($param, $store = null)
    {
        $configVal = $this->scopeConfig->getValue(
            self::XML_PATH_ADVANCED . $param,
            ScopeInterface::SCOPE_STORE,
            $store
        );

        return $configVal;
    }

    /**
     * @param string $sku
     * @return void
     */
    protected function collectApiUrlParams($sku)
    {
        foreach ($this->requestParams as $key => $param) {
            $conf = $this->extractParam($param);
            $conf = $conf ? : $param;
            $this->apiQuery[$key] = $conf;
        }

        $this->apiQuery['SKU'] = $sku;
    }

    /**
     * @param string $sku
     * @return string
     */
    protected function prepareApiUrl($sku): string
    {
        if ($host = $this->extractParam(self::XML_PATH_HOST_PARAM)) {
            $uri = UriFactory::factory('https:');
            $uri->setHost($this->extractParam(self::XML_PATH_HOST_PARAM));
            $uri->setPath(self::API_URL_PATH);
            $this->collectApiUrlParams($sku);
            $uri->setQuery($this->apiQuery);
            $this->apiUrl = $uri->toString();
        }

        return (string)$this->apiUrl;
    }

    /**
     * @param array $result
     * @return bool
     */
    protected function checkStatus(array $result)
    {
        if (
            isset($result['Status'])
            && $result['Status'] == self::API_SUCCESS_STATUS
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param array $result
     * @return array
     */
    protected function extractItemIds(array $result, int $limit): array
    {
        if (isset($result['Items'])) {
            $skus = [];
            foreach ($result['Items'] as $item) {
                if (
                    isset($item['Fields'])
                    && isset($item['Fields']['Rank'])
                    && isset($item['Fields']['SKU'])
                ) {
                    $skus[$item['Fields']['Rank']] = $item['Fields']['SKU'];
                }
            }

            ksort($skus);
            if ($limit) {
                $skus = array_slice($skus, 0, $limit);
            }

            return $skus;
        }

        return [];
    }

    /**
     * @param array $message
     * @return void
     */
    protected function sendDebugMessage(array $message)
    {
        if ($this->isRequestDebug()) {
            $this->messageManager->addSuccess(
                $this->prepareDebugMessage($message)
            );
        }
    }

    /**
     * @param array $message
     * @return void
     */
    protected function sendErrorMessage(array $message)
    {
        $this->messageManager->addError(
            $this->prepareDebugMessage($message)
        );
    }

    /**
     * @param string $sku
     * @param int $limit
     * @param string $type
     * @return array
     */
    public function getRecommendedIds($sku, int $limit = 0, string $type = 'crosssell'): array
    {
        $cacheId = $this->cache->getId(
            __METHOD__,
            [$sku, $type]
        );
        $this->prepareApiUrl($sku);
        if ($this->apiUrl) {
            $arrIds = array();
            $startTime = round(microtime(true) * 1000);

            try {
                $this->curl->get($this->apiUrl, []);
            } catch (\Exception $ex) {
                $this->sendErrorMessage([
                    'message' => $ex->getMessage()
                ]);

                return [];
            }

            if ($response = $this->cache->load($cacheId)) {
                $this->sendDebugMessage([
                    'request' => $this->apiUrl,
                    'cached' => 'TRUE'
                ]);

                return explode(",", (string)$response);
            } else {
                $stime = round(microtime(true) * 1000) - $startTime;
                $this->sendDebugMessage([
                    'request' => $this->apiUrl,
                    'cached' => 'FALSE',
                    'duration' => $stime . 'ms'
                ]);

                $result = (array)$this->jsonHelper->jsonDecode($this->curl->getBody());
                if ($this->checkStatus($result)) {
                    $ids = $this->extractItemIds($result, $limit);
                    $this->cache->save(implode(",", $ids), $cacheId);
                    return $ids;
                }
            }
        } else {
            $this->sendErrorMessage([
                'message' => __('Crosssell API url is not defined')
            ]);
        }

        return [];
    }
}
