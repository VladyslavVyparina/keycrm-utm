<?php

namespace KeyCRM\Service;

use KeyCRM\Base;
use KeyCRM\Service\Converter\OrderConverter;

class OrderManager extends Base
{
    protected $registry;
    /** @var \ModelAccountOrder */
    protected $model_checkout_order;
    /** @var \ModelCheckoutOrder */
    protected $model_account_order;
    /** @var \ModelSaleOrder */
    protected $model_sale_order;
    /** @var \ModelSettingSetting */
    protected $model_setting_setting;
    /** @var \ModelCatalogProduct */
    protected $model_catalog_product;
    /** @var \ModelExtensionModuleKeycrm */
    protected $model_extension_module_keycrm;
    /** @var \ModelToolImage */
    protected $model_tool_image;
    /** @var OrderConverter */
    protected $orderConverter;

    public function __construct(\Registry $registry)
    {
        parent::__construct($registry);

        $this->load->library('keycrm/keycrm');

        if ($this->keycrm->isAdmin()) {
            $this->model_sale_order = $this->loadModel('sale/order');
        } else {
            $this->model_account_order = $this->loadModel('account/order');
            $this->model_checkout_order = $this->loadModel('checkout/order');
        }

        $this->model_catalog_product = $this->loadModel('catalog/product');
        $this->model_setting_setting = $this->loadModel('setting/setting');
        $this->model_extension_module_keycrm = $this->loadModel('extension/module/keycrm');
        $this->model_tool_image = $this->loadModel('tool/image');

        $this->orderConverter = new OrderConverter($this->keycrm);
    }

    public function prepareOrderImportRequest($order_ids)
    {
        $result = [];
        foreach ($order_ids as $order_id) {
            $result[] = $this->prepareOrder($order_id);
        }

        $result = array_filter($result);
        if (! $result) {
            return null;
        }

        return json_encode(['orders' => $result], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function prepareOrderRequest($order_id)
    {
        $order = $this->prepareOrder($order_id);
        if (! $order) {
            return null;
        }

        return json_encode($order, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    protected function prepareOrder($order_id)
    {
        $ocOrder = $this->keycrm->isAdmin() ?
            $this->model_sale_order->getOrder($order_id) :
            $this->model_checkout_order->getOrder($order_id);

        // file_put_contents(DIR_LOGS . 'debug.log', print_r($ocOrder, true), FILE_APPEND);

        if (! $ocOrder) {
            return null;
        }

        $source_id = $this->getSourceId($ocOrder);
        if (! $source_id) {
            return null;
        }

        $ocProducts = $this->keycrm->isAdmin() ?
            $this->model_sale_order->getOrderProducts($order_id) :
            $this->model_account_order->getOrderProducts($order_id);

        $ocProducts = $this->loadProductsOptions($order_id, $ocProducts);
        $ocProducts = $this->loadOffers($ocProducts);
        $totals = $this->keycrm->isAdmin() ?
            $this->model_sale_order->getOrderTotals($order_id) :
            $this->model_account_order->getOrderTotals($order_id);

        $ocOrder['coupon_info'] = $this->model_extension_module_keycrm->getCouponHistoryInfo($order_id);

        $settings = $this->model_setting_setting
            ->getSetting($this->keycrm->getModuleTitle());

        $order = $this->orderConverter->getKeyCRMOrder($settings, $source_id, $ocOrder, $ocProducts, $totals);

        $utm = [];

        if (!empty($this->session->data['keycrm_utm']) && is_array($this->session->data['keycrm_utm'])) {
            $utm = $this->session->data['keycrm_utm'];
        } elseif (!empty($_COOKIE['keycrm_utm'])) {
            $raw = (string)$_COOKIE['keycrm_utm'];
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                $decoded = json_decode(urldecode($raw), true);
            }
            if (is_array($decoded)) {
                $utm = $decoded;
            }
        }

        $marketing = array_filter([
            'utm_source'   => (string)($utm['utm_source'] ?? ''),
            'utm_medium'   => (string)($utm['utm_medium'] ?? ''),
            'utm_campaign' => (string)($utm['utm_campaign'] ?? ''),
            'utm_term'     => (string)($utm['utm_term'] ?? ''),
            'utm_content'  => (string)($utm['utm_content'] ?? ''),
        ], function($v) { return $v !== ''; });

        if ($order !== null && !empty($marketing)) {
            $order->marketing = $marketing;
        }

        // DEBUG log
        file_put_contents(
            DIR_LOGS . 'keycrm_utm_debug.log',
            date('Y-m-d H:i:s')
                . ' order_id=' . $order_id
                . ' isAdmin=' . ($this->keycrm->isAdmin() ? 'YES' : 'NO')
                . ' UTM=' . json_encode($utm)
                . ' MARKETING=' . json_encode($marketing)
                . "\n",
            FILE_APPEND
        );

        return $order;
    }

    /**
     * @param int   $order_id
     * @param array $ocProducts
     *
     * @return array
     */
    protected function loadProductsOptions($order_id, $ocProducts)
    {
        foreach ($ocProducts as $key => $product) {
            $productOptions = $this->keycrm->isAdmin() ?
                $this->model_sale_order->getOrderOptions($order_id, $product['order_product_id']) :
                $this->model_account_order->getOrderOptions($order_id, $product['order_product_id']);

            if (! empty($productOptions)) {
                $ocProducts[$key]['options'] = $productOptions;
            }
        }

        return $ocProducts;
    }

    /**
     * @param array $ocProducts
     *
     * @returns array
     */
    protected function loadOffers($ocProducts)
    {
        foreach ($ocProducts as $key => $product) {
            $product = $this->model_catalog_product->getProduct($product['product_id']);

            if (! empty($product)) {
                $product['image'] = $this->generateImage($product['image']);
                $ocProducts[$key]['offer'] = $product;
            }
        }

        return $ocProducts;
    }

    protected function getSourceId($ocOrder)
    {
        $storeId = $ocOrder['store_id'];

        $settings = $this->model_setting_setting
            ->getSetting($this->keycrm->getModuleTitle());
        $stores = $settings[$this->keycrm->getModuleTitle().'_stores'];

        $filtered = array_values(array_filter($stores, static function($item) use ($storeId) {
            return $item['store_id'] === $storeId && $item['enabled'] === 'on';
        }));

        return count($filtered) ? $filtered[0]['source_id'] : null;
    }

    protected function generateImage($url)
    {
        if (! $url) {
            return null;
        }

        $currentTheme = $this->config->get('config_theme');
        $width = $this->config->get($currentTheme . '_image_related_width') ?
            $this->config->get($currentTheme . '_image_related_width') : 400;

        $height = $this->config->get($currentTheme . '_image_related_height') ?
            $this->config->get($currentTheme . '_image_related_height') : 400;

        return $this->model_tool_image->resize(
            $url,
            $width,
            $height
        );
    }
}
