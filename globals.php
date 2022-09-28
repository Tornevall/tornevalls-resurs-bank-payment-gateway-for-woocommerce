<?php

use ResursBank\Module\Data;

if (!function_exists('rbwc_get_option')) {
    /**
     * @param string $key
     * @param string $namespace
     * @param bool $getDefaults
     * @return bool|mixed|string|null
     * @since 0.0.1.8
     */
    function rbwc_get_option(
        string $key,
        string $namespace = '',
        bool $getDefaults = true
    ) {
        return Data::getResursOption(
            $key,
            $namespace,
            $getDefaults
        );
    }
}

if (!function_exists('rbwc_get_prefix')) {
    /**
     * @param $extra
     * @param $ignoreCodeBase
     * @return string
     * @since 0.0.1.8
     */
    function rbwc_get_prefix($extra = null, $ignoreCodeBase = null)
    {
        return Data::getPrefix($extra, $ignoreCodeBase);
    }
}

if (!function_exists('rbwc_get_truth')) {
    /**
     * @param $value
     * @return bool|null
     * @since 0.0.1.8
     */
    function rbwc_get_truth($value) {
        return Data::getTruth($value);
    }
}

if (!function_exists('rbwc_can_handle_order')) {
    /**
     * @param $paymentMethod
     * @return bool
     * @since 0.0.1.8
     */
    function rbwc_can_handle_order($paymentMethod) {
        return Data::canHandleOrder($paymentMethod);
    }
}

if (!function_exists('rbwc_get_order_info')) {
    /**
     * @param $order
     * @return array|mixed
     * @throws ResursException
     * @since 0.0.1.8
     */
    function rbwc_get_order_info($order) {
        return Data::getOrderInfo($order);
    }
}

if (!function_exists('rbwc_get_escaped_html')) {
    /**
     * @param $html
     * @return string
     * @since 0.0.1.8
     */
    function rbwc_get_escaped_html($html) {
        return Data::getEscapedHtml($html);
    }
}
