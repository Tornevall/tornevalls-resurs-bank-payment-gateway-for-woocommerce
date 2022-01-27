<?php

namespace ResursBank\Module;

use Exception;
use ResursBank\Gateway\ResursDefault;
use ResursBank\Service\WooCommerce;
use TorneLIB\Utils\WordPress;
use function is_array;

/**
 * Class Plugin Internal plugin handler.
 *
 * @package ResursBank\Module
 */
class PluginHooks
{
    public function __construct()
    {
        add_filter('rbwc_js_loaders_checkout', [$this, 'getRcoLoaderScripts']);
        add_filter('rbwc_get_payment_method_icon', [$this, 'getMethodIconByContent'], 10, 2);
        add_filter('rbwc_part_payment_string', [$this, 'getPartPaymentWidgetPage'], 10, 2);
        add_filter('rbwc_get_order_note_prefix', [$this, 'getDefaultOrderNotePrefix'], 1);
        add_action('rbwc_mock_update_payment_reference_failure', [$this, 'mockUpdatePaymentFailure']);
        add_action('rbwc_mock_create_iframe_exception', [$this, 'mockCreateIframeException']);
        add_action('rbwc_mock_callback_update_exception', [$this, 'mockCallbackUpdateException']);
        add_action('rbwc_mock_get_payment_methods_exception', [$this, 'mockGetPaymentMethodsException']);
        add_action('rbwc_mock_annuity_factor_config_exception', [$this, 'mockAnnuityFactorConfigException']);
        add_action('rbwc_mock_empty_price_info_html', [$this, 'mockEmptyPriceInfoHtml']);
        add_action('mock_update_callback_exception', [$this, 'mockUpdateCallbackException']);
        add_filter('resursbank_temporary_disable_checkout', [$this, 'setRcoDisabledWarning'], 99999, 1);
        add_filter('rbwc_get_available_auto_debit_methods', [$this, 'getAvailableAutoDebitMethods']);
        add_action('rbwc_update_order_status_by_queue', [$this, 'updateOrderStatusByQueue'], 10, 3);
        add_action('woocommerce_order_status_changed', [$this, 'updateOrderStatusByWooCommerce'], 10, 3);
    }

    /**
     * @param $key
     * @return int|mixed|null
     */
    public function getUserInfo($key)
    {
        $return = null;

        if (function_exists('get_current_user_id') && function_exists('get_user_meta')) {
            $currentUserId = get_current_user_id();
            if ($key === 'userid' || empty($key)) {
                $return = $currentUserId;
            } else {
                $metaData = empty($key) ? get_user_meta($currentUserId) : get_user_meta($currentUserId, $key);
                if ($currentUserId && is_array($metaData) && !count($metaData)) {
                    $return = get_userdata($currentUserId)->get($key);
                } else {
                    $return = isset($metaData[$key]) ? $metaData[$key]:$metaData;
                    if (is_array($return) && count($return) === 1) {
                        $return = array_pop($return);
                    }
                }
            }
        }

        return $return;
    }

    /**
     * @param $orderId
     * @param $oldSlug
     * @param $newSlug
     * @throws Exception
     * @since 0.0.1.0
     */
    public function updateOrderStatusByWooCommerce($orderId, $oldSlug, $newSlug)
    {
        if (($order = Data::getResursOrderIfExists($orderId))) {
            // Userdata that should follow with the afterShopFlow when changing order status on Resurs side,
            // for backtracking actions.

            $wpHelper = new WordPress();
            $currentRunningUser = $wpHelper->getUserInfo(null);;
            $currentRunningUsername = $wpHelper->getUserInfo('user_login');

            // This is where we handle order statuses changed from WooCommerce.
        }
    }

    /**
     * Queued status handler. Should not be called directly as it is based on WC_Queue.
     *
     * @param $order
     * @param $status
     * @param $notice
     * @throws Exception
     * @since 0.0.1.0
     * @link https://github.com/woocommerce/woocommerce/wiki/WC_Queue---WooCommerce-Worker-Queue
     */
    public function updateOrderStatusByQueue($order = '', $status = '', $notice = '')
    {
        if (!empty($status)) {
            $properOrder = WooCommerce::getProperOrder($order, 'order');

            $currentStatus = $properOrder->get_status();
            if ($currentStatus !== $status) {
                $properOrder->update_status(
                    $status,
                    WooCommerce::getOrderNotePrefixed($notice)
                );
                Data::canLog(
                    Data::CAN_LOG_ORDER_EVENTS,
                    sprintf(
                        __(
                            'Queued Status Handler: Updated status for %s to %s with notice: %s',
                            'trbwc'
                        ),
                        $order,
                        $status,
                        $notice
                    )
                );
            } else {
                Data::canLog(
                    Data::CAN_LOG_ORDER_EVENTS,
                    sprintf(
                        __(
                            'Queued Status Handler: Status for %s not updated to %s, because that ' .
                            'status was already set.',
                            'trbwc'
                        ),
                        $order,
                        $status
                    )
                );
            }
        }
    }

    /**
     * @return string
     * @since 0.0.1.0
     */
    public function mockEmptyPriceInfoHtml()
    {
        return '';
    }

    /**
     * @param $return
     * @return mixed
     * @throws Exception
     * @since 0.0.1.0
     */
    public function getAvailableAutoDebitMethods($return)
    {
        WooCommerce::setSessionValue('rb_requesting_debit_methods', true);
        // If we are saving or are somewhere else than in the payment methods section, we don't need
        // to run this controller as it is only used for visuals.
        if (!isset($_REQUEST['save']) &&
            isset($_REQUEST['section']) &&
            $_REQUEST['section'] === 'payment_methods'
        ) {
            try {
                // Get payment methods locally for this request. We don't have to fetch
                // this live each run for auto debitable methods.
                $paymentMethodList = ResursBankAPI::getPaymentMethods(true);
            } catch (Exception $e) {
                $return = [
                    'default' => __('Payment Methods are currently unavailable!', 'trbwc'),
                ];
            }
            if (isset($paymentMethodList) && is_array($paymentMethodList)) {
                $return['default'] = __('Default (Choice made by plugin)', 'trbwc');
                foreach ($paymentMethodList as $method) {
                    if ($method->type === 'PAYMENT_PROVIDER') {
                        $return[$method->specificType] = $method->specificType;
                    }
                }
            }
        }
        WooCommerce::setSessionValue('rb_requesting_debit_methods', false);

        return $return;
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public function mockCreateIframeException()
    {
        $this->getMockException(__FUNCTION__);
    }

    /**
     * @throws Exception
     * @since 1.0.0
     */
    public function mockCallbackUpdateException()
    {
        $this->getMockException(__FUNCTION__);
    }

    /**
     * @throws Exception
     * @since 1.0.0
     */
    public function mockGetPaymentMethodsException()
    {
        $this->getMockException(__FUNCTION__);
    }

    /**
     * @throws Exception
     * @since 1.0.0
     */
    public function mockAnnuityFactorConfigException()
    {
        $this->getMockException(__FUNCTION__);
    }

    /**
     * @param $function
     * @throws Exception
     * @since 0.0.1.0
     */
    private function getMockException($function)
    {
        $exceptionCode = 470;
        Data::canLog(
            Data::LOG_INFO,
            sprintf(
                __('Mocked Exception in action. Throwing MockException for function %s, with error code %d.'),
                $function,
                $exceptionCode
            )
        );

        throw new Exception(
            sprintf(
                'MockException: %s',
                $function
            ),
            $exceptionCode
        );
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public function mockUpdateCallbackException()
    {
        $this->getMockException(__FUNCTION__);
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public function mockUpdatePaymentFailure()
    {
        $this->getMockException(__FUNCTION__);
    }

    /**
     * @param $defaultPrefix
     * @return mixed
     * @since 0.0.1.0
     */
    public function getDefaultOrderNotePrefix($defaultPrefix)
    {
        if (!empty(Data::getResursOption('order_note_prefix'))) {
            $defaultPrefix = Data::getResursOption('order_note_prefix');
        }
        return $defaultPrefix;
    }

    /**
     * Custom content for part payment data.
     * @return string
     * @since 0.0.1.0
     */
    public function getPartPaymentWidgetPage($return)
    {
        $partPaymentWidgetId = Data::getResursOption('part_payment_template');
        if ($partPaymentWidgetId) {
            $return = get_post($partPaymentWidgetId)->post_content;
        }

        return $return;
    }

    /**
     * @param $url
     * @param $methodInformation
     * @since 0.0.1.0
     * @noinspection NotOptimalRegularExpressionsInspection
     */
    public function getMethodIconByContent($url, $methodInformation)
    {
        $iconSetting = Data::getResursOption('payment_method_icons');
        foreach ($methodInformation as $item) {
            $itemName = strtolower($item);
            if (preg_match('/^pspcard_/i', strtolower($item))) {
                // Shorten up credit cards.
                $itemName = 'pspcard';
            }
            $byItem = sprintf('method_%s.png', $itemName);

            if (($imageByMethodContent = Data::getImage($byItem))) {
                $url = $imageByMethodContent;
                break;
            }
        }

        if (empty($url) &&
            $iconSetting === 'specifics_and_resurs' &&
            $methodInformation['type'] !== 'PAYMENT_PROVIDER'
        ) {
            $url = Data::getImage('resurs-logo.png');
        }

        return $url;
    }

    /**
     * @param $filterIsActive
     */
    public function setRcoDisabledWarning($filterIsActive)
    {
        if ($filterIsActive) {
            Data::setLogInternal(
                Data::LOG_WARNING,
                sprintf(
                    __(
                        'The filter "%s" is currently put in an active state by an unknown third party plugin. This ' .
                        'setting is deprecated and no longer fully supported. It is highly recommended to disable ' .
                        'or remove the filter entirely and solve the problem that required this from start somehow ' .
                        'else.',
                        'trbwc'
                    ),
                    'resursbank_temporary_disable_checkout'
                )
            );
        }
    }

    /**
     * @param $scriptList
     * @return mixed
     * @since 0.0.1.0
     */
    public function getRcoLoaderScripts($scriptList)
    {
        if (Data::getCheckoutType() === ResursDefault::TYPE_RCO) {
            $scriptList['resursbank_rco_legacy'] = 'resurscheckoutjs/resurscheckout.js';
        }

        return $scriptList;
    }
}
