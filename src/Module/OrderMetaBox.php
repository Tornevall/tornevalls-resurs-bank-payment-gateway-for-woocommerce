<?php

namespace ResursBank\Module;

use Exception;
use Resursbank\Ecom\Lib\Log\LogLevel;
use ResursBank\Gateway\ResursDefault;
use ResursBank\Service\WooCommerce;
use ResursBank\Service\WordPress;
use ResursException;
use WC_Order;
use WP_Post;
use function is_array;

/**
 * @since 0.0.1.7
 */
class OrderMetaBox
{
    /**
     * @param WP_Post $post
     * @throws ResursException
     * @throws Exception
     * @since 0.0.1.7
     */
    public static function output_order($post)
    {
        if (!$post instanceof WP_Post && $post->post_type !== 'shop_order') {
            return;
        }
        $order = new WC_Order($post->ID);
        $paymentMethod = $order->get_payment_method();

        if (Data::canHandleOrder($paymentMethod)) {
            $orderData = Data::getOrderInfo($order);
            self::setOrderMetaInformation($orderData);
            $orderData['ecom_meta'] = [];
            if (!isset($orderData['ecom'])) {
                $orderData['ecom'] = [];
            }
            if (!isset($orderData['ecom_short'])) {
                $orderData['ecom_short'] = [];
            }

            if (isset($orderData['meta']) && is_array($orderData['meta'])) {
                $orderData['ecom_short'] = WooCommerce::getMetaDataFromOrder(
                    $orderData['ecom_short'],
                    []
                );
            }
            $orderData['v2'] = Data::isDeprecatedPluginOrder($paymentMethod) ? true : false;
            if (Data::getCheckoutType() === ResursDefault::TYPE_RCO) {
                $orderData['ecom_short']['ecom_had_reference_problems'] =
                    WooCommerce::getEcomHadProblemsInfo($orderData);
            }

            echo Data::getEscapedHtml(
                content: Data::getGenericClass()->getTemplate(
                    templateName: 'adminpage_details.phtml',
                    assignedVariables: $orderData
                )
            );
            WordPress::doAction(actionName: 'showOrderDetails', value: $orderData);
        }
    }

    /**
     * @param WP_Post $post
     * @return void
     * @throws ResursException
     */
    public static function output_meta_details(WP_Post $post): void
    {
        $order = new WC_Order($post->ID);
        $paymentMethod = $order->get_payment_method();

        if (Data::canHandleOrder($paymentMethod)) {
            $orderData = Data::getOrderInfo($order);
            self::setOrderMetaInformation($orderData);
            $orderData['ecom_meta'] = [];

            if (!isset($orderData['ecom'])) {
                $orderData['ecom'] = [];
                $orderData['ecom_short'] = [];
            }
            if (isset($orderData['meta']) && is_array($orderData['meta'])) {
                $orderData['ecom_short'] = WooCommerce::getMetaDataFromOrder(
                    [],
                    $orderData['meta'] ?? []
                );
            }
            $orderData['v2'] = Data::isDeprecatedPluginOrder($paymentMethod) ? true : false;
            if (Data::getCheckoutType() === ResursDefault::TYPE_RCO) {
                $orderData['ecom_short']['ecom_had_reference_problems'] =
                    WooCommerce::getEcomHadProblemsInfo($orderData);
            }

            echo Data::getEscapedHtml(
                Data::getGenericClass()->getTemplate('adminpage_details.phtml', $orderData)
            );
            WordPress::doAction('showOrderDetails', $orderData);
        }
    }

    /**
     * setOrderMetaInformation prepares and sets data on the fly.
     *
     * Moved from 0.0.1.0 to 0.0.1.7 (this class).
     * @param $orderData
     * @throws Exception
     * @throws ResursException
     * @since 0.0.1.0
     * @since 0.0.1.7
     */
    private static function setOrderMetaInformation($orderData)
    {
        if (isset($orderData['ecom_short']) &&
            is_array($orderData['ecom_short']) &&
            count($orderData['ecom_short'])
        ) {
            $login = Data::getResursOption('login');
            $password = Data::getResursOption('password');
            if (!empty($password) &&
                !empty($login) &&
                Data::getResursOption('store_api_history') &&
                !Data::getOrderMeta('orderapi', $orderData['order'])) {
                Data::writeLogByLogLevel(
                    LogLevel::INFO,
                    sprintf(
                        __(
                            'EComPHP data present. Saving metadata for order %s.',
                            'resurs-bank-payments-for-woocommerce'
                        ),
                        $orderData['order']->get_id()
                    )
                );

                // Set encrypted order meta data with api credentials that belongs to this order.
                Data::setOrderMeta(
                    $orderData['order'],
                    'orderapi',
                    Data::setEncryptData(
                        json_encode(
                            [
                                'l' => $login,
                                'p' => $password,
                                'e' => Data::getResursOption('environment'),
                            ]
                        )
                    )
                );
            }
        }
    }
}
