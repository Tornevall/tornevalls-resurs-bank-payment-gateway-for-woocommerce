<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Order\Controller\Admin;

use JsonException;
use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Modules\PaymentInformation\PaymentInformation;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Metadata;
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
     * @throws EmptyValueException
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    // phpcs:ignore
    public static function exec(): string
    {
        $orderId = $_GET['orderId'] ?? null;
        $orderId = (int) $orderId;

        if ($orderId === 0) {
            throw new HttpException(message: 'Missing order id.');
        }

        // Look for order types set by WC. If none is set, WC will fail reading the order properly.
        // abstract-wc-order-data-store-cpt.php
        $orderTypes = wc_get_order_types();

        if (!$orderTypes) {
            add_filter('wc_order_types', static fn () => ['shop_order'], 10, 2);
        }

        $order = OrderManagement::getOrder(id: $orderId);

        if ($order === null || !Metadata::isValidResursPayment(order: $order)) {
            throw new HttpException(message: 'Invalid order id.');
        }

        $data = [];

        try {
            $data['payment_info'] = (new PaymentInformation(
                paymentId: Metadata::getPaymentId(order: $order)
            ))->widget->content;
        } catch (Throwable $error) {
            Log::error(error: $error);
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
            Log::error(error: $error);
        }

        try {
            $data['messages'] = json_encode(
                value: MessageBag::getBag(),
                flags: JSON_THROW_ON_ERROR
            );

            MessageBag::clear();
        } catch (Throwable $error) {
            Log::error(error: $error);
        }

        return json_encode(value: $data, flags: JSON_THROW_ON_ERROR);
    }
}
