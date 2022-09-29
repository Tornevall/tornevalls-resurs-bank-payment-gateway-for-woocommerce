<?php

use ResursBank\Module\Data;
use ResursBank\Service\WooCommerce;

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
    function rbwc_get_prefix($extra = null, $ignoreCodeBase = null): string
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
    function rbwc_get_truth($value): ?bool
    {
        return Data::getTruth($value);
    }
}

if (!function_exists('rbwc_can_handle_order')) {
    /**
     * @param $paymentMethod
     * @return bool
     * @since 0.0.1.8
     */
    function rbwc_can_handle_order($paymentMethod): bool
    {
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
    function rbwc_get_escaped_html($html): string
    {
        return Data::getEscapedHtml($html);
    }
}

if (!function_exists('rbwc_get_order_meta')) {
    /**
     * Get metadata values from specific order, by key name.
     *
     * @param string $key
     * @param $order
     * @return mixed|null
     * @throws ResursException
     * @since 0.0.1.8
     */
    function rbwc_get_order_meta(string $key, $order)
    {
        return Data::getOrderMeta($key, $order);
    }
}

if (!function_exists('rbwc_log_info')) {
    /**
     * @param string $message
     * @return void
     * @since 0.0.1.8
     */
    function rbwc_log_info(string $message)
    {
        Data::setLogInfo($message);
    }
}

if (!function_exists('rbwc_log_error')) {
    /**
     * @param string $logMessage
     * @return void
     * @since 0.0.1.8
     */
    function rbwc_log_error(string $logMessage)
    {
        Data::setLogError($logMessage);
    }
}

if (!function_exists('rbwc_log_exception')) {
    /**
     * @param Exception $exception
     * @param string $fromFunction
     * @return void
     * @since 0.0.1.8
     */
    function rbwc_log_exception(Exception $exception, string $fromFunction = '')
    {
        Data::setLogException($exception, $fromFunction);
    }
}

if (!function_exists('rbwc_apply_mock')) {
    /**
     * Apply a specific filter used for mocking.
     * @param $mock
     * @return mixed|null
     * @since 0.0.1.8
     */
    function rbwc_apply_mock($mock) {
        return WooCommerce::applyMock($mock);
    }
}
