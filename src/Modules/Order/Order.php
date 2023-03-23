<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Order;

use Automattic\WooCommerce\Admin\PageController;
use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\CacheException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Model\PaymentMethod;
use Resursbank\Ecom\Module\PaymentMethod\Repository;
use Resursbank\Woocommerce\Database\Options\Advanced\StoreId;
use Resursbank\Woocommerce\Modules\PaymentInformation\PaymentInformation;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;
use WC_Order;
use WP_Post;

/**
 * WC_Order related business logic.
 */
class Order
{
    /**
     * Initialize Order module.
     */
    public static function init(): void
    {
        add_action(
            hook_name: 'add_meta_boxes',
            callback: 'Resursbank\Woocommerce\Modules\Order\Order::addPaymentInfo'
        );
        add_filter(
            hook_name: 'is_protected_meta',
            callback: 'Resursbank\Woocommerce\Modules\Order\Order::hideCustomFields',
            priority: 10,
            accepted_args: 2
        );
    }

    /**
     * Add action which will render payment information on order view.
     */
    public static function addPaymentInfo(): void
    {
        add_meta_box(
            id: 'resursbank_payment_info',
            title: 'Resurs',
            callback: 'Resursbank\Woocommerce\Modules\Order\Order::renderPaymentInfo'
        );
    }

    /**
     * Render payment information box on order view.
     */
    public static function renderPaymentInfo(): void
    {
        global $post;

        if (
            !$post instanceof WP_Post ||
            $post->post_type !== 'shop_order' ||
            (new PageController())->get_current_screen_id() !== 'shop_order'
        ) {
            return;
        }

        $order = new WC_Order(order: $post->ID);

        if (
            !$order instanceof WC_Order
        ) {
            return;
        }

        try {
            $paymentInformation = new PaymentInformation(
                paymentId: Metadata::getPaymentId(order: $order)
            );

            $data = $paymentInformation->widget->content;
        } catch (Throwable $e) {
            $data = '<b>' .
                Translator::translate(
                    phraseId: 'failed-to-fetch-payment-data-from-the-server'
                ) . ' ' .
                Translator::translate(
                    phraseId: 'reason'
                ) . ':</b> ' . $e->getMessage();

            Log::error(error: $e);
        }

        echo $data;
    }

    /**
     * Hide the plugin's custom fields from view.
     *
     * @SuppressWarnings(PHPMD.CamelCaseParameterName)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     */
    public static function hideCustomFields(mixed $protected, mixed $meta_key): mixed
    {
        if (
            str_starts_with(
                haystack: $meta_key,
                needle: RESURSBANK_MODULE_PREFIX . '_'
            )
        ) {
            return true;
        }

        return $protected;
    }

    /**
     * @throws ValidationException
     * @throws AuthException
     * @throws EmptyValueException
     * @throws CurlException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ConfigException
     * @throws IllegalTypeException
     * @throws ReflectionException
     * @throws ApiException
     * @throws CacheException
     */
    public static function getPaymentMethod(
        WC_Order $order
    ): ?PaymentMethod {
        $method = (string) $order->get_payment_method();

        if ($method === '') {
            return null;
        }

        return Repository::getById(
            storeId: StoreId::getData(),
            paymentMethodId: $method
        );
    }
}
