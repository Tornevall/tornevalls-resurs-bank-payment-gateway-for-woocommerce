<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Order;

use Automattic\WooCommerce\Admin\PageController;
use Exception;
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
use Resursbank\Woocommerce\Modules\Order\Filter\DeleteItem;
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
        DeleteItem::register();
        add_action(
            hook_name: 'add_meta_boxes',
            callback: 'Resursbank\Woocommerce\Modules\Order\Order::addPaymentInfo'
        );
    }

    /**
     * Resolve order matching item id if it exists, implements WC_Order, and
     * contains metadata indicating it was purchased through Resurs Bank.
     *
     * @return WC_Order|null WC_Order if paid using Resurs Bank.
     * @throws Exception
     */
    public static function getOrder(int $itemId): ?WC_Order
    {
        $order = null;

        try {
            $order = wc_get_order(
                the_order: wc_get_order_id_by_order_item_id(item_id: $itemId)
            );
        } catch (Throwable) {
            // Do nothing. Next if-statement will exit anyway.
        }

        return (
            $order instanceof WC_Order &&
            Metadata::isValidResursPayment(order:$order)
        ) ? $order : null;
    }

    /**
     * Get Resurs Bank payment id attached to order.
     *
     * @throws IllegalValueException
     */
    public static function getPaymentId(WC_Order $order): string
    {
        $id = Metadata::getPaymentId(order: $order);

        if ($id === '') {
            throw new IllegalValueException(
                message: 'Missing Resurs Bank payment UUID.'
            );
        }

        return $id;
    }

    /**
     * Add action which will render payment information on order view.
     */
    public static function addPaymentInfo(): void
    {
        add_meta_box(
            id: 'resursbank_orderinfo',
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
            !$order instanceof WC_Order ||
            !Metadata::isValidResursPayment(order: $order)
        ) {
            return;
        }

        $data = '';

        try {
            $paymentInformation = new PaymentInformation(
                paymentId: Metadata::getPaymentId(order: $order)
            );

            $data = $paymentInformation->widget->content;
        } catch (Throwable $e) {
            $data = '<b>' .
                Translator::translate(
                    phraseId: 'failed-to-fetch-order-data-from-the-server'
                ) . ' ' .
                Translator::translate(
                    phraseId: 'server-response'
                ) . ':</b> ' . $e->getMessage();

            Log::error(error: $e);
        }

        echo $data;
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
