<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Order\Controller\Admin;

use JsonException;
use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Modules\PaymentInformation\PaymentInformation;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Ecom\Lib\Log\Logger;
use Throwable;

use function constant;

/**
 * Fetch new content for order view after order updates.
 */
class GetOrderContentController
{
    /**
     * @throws HttpException
     * @throws JsonException
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    // phpcs:ignore
    public static function exec(): string
    {
        $orderId = $_GET['orderId'] ?? null;
        $orderId = (int)$orderId;

        if ($orderId === 0) {
            throw new HttpException(message: 'Missing order id.');
        }

        // Look for order types set by WC. If none is set, WC will fail reading the order properly.
        // abstract-wc-order-data-store-cpt.php
        $orderTypes = wc_get_order_types();

        if (!$orderTypes) {
            add_filter(
                'wc_order_types',
                static fn ($types) => array_merge($types, ['shop_order']),
                10,
                2
            );
        }

        // Make sure we don't allow orde fetching before post types are registered.
        if (!did_action('woocommerce_after_register_post_type')) {
            $data = [
                'payment_info' => null,
                'error' => 'doing_it_wrong_woocommerce_after_register_post_type'
            ];
            return json_encode(value: $data, flags: JSON_THROW_ON_ERROR);
        }

        $order = OrderManagement::getOrder(id: $orderId);

        if ($order === null || !Metadata::isValidResursPayment(order: $order)) {
            throw new HttpException(message: 'Invalid order id.');
        }

        $data = [];

        try {
            $data['payment_info'] = PaymentInformation::getWidgetHtml(
                paymentId: Metadata::getPaymentId(order: $order)
            );
        } catch (Throwable $error) {
            Logger::error(message: $error);
        }

        try {
            $file = constant(
                name: 'WC_ABSPATH'
            ) . '/includes/admin/meta-boxes/views/html-order-notes.php';

            if (!file_exists(filename: $file)) {
                throw new HttpException(
                    message: 'Missing order notes template.'
                );
            }

            ob_start();
            /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
            // phpcs:ignore
            $notes = wc_get_order_notes(['order_id' => $order->get_id()]);
            include $file;
            $data['order_notes'] = ob_get_clean();
        } catch (HttpException $error) {
            throw $error;
        } catch (Throwable $error) {
            Logger::error(message: $error);
        }

        return json_encode(value: $data, flags: JSON_THROW_ON_ERROR);
    }
}
