<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Order;

use Resursbank\Ecom\Lib\Log\Logger;
use Resursbank\Woocommerce\Modules\PaymentInformation\PaymentInformation;
use Resursbank\Woocommerce\Util\Admin;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\RouteVariant;
use Resursbank\Woocommerce\Util\Translator;
use Resursbank\Woocommerce\Util\Url;
use Throwable;
use WC_Order;

/**
 * WC_Order related business logic.
 */
class Order
{
    /**
     * Initialize Order module.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function init(): void
    {
        add_action(
            'add_meta_boxes',
            'Resursbank\Woocommerce\Modules\Order\Order::addPaymentInfo'
        );
        add_filter(
            'is_protected_meta',
            'Resursbank\Woocommerce\Modules\Order\Order::hideCustomFields',
            10,
            2
        );
    }

    /**
     * Add action which will render payment information on order view.
     *
     * @SuppressWarnings(PHPMD.EmptyCatchBlock)
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function addPaymentInfo(): void
    {
        try {
            $order = wc_get_order();
        } catch (Throwable) {
            // wc_get_order is a WooCommerce owned method that normally returns false on errors.
            // They should not be necessary to log.
            return;
        }

        if (
            !($order instanceof WC_Order) ||
            !Metadata::isValidResursPayment(order: $order)
        ) {
            return;
        }

        add_meta_box(
            'resursbank_payment_info',
            'Resurs',
            static function () use ($order): void {
                try {
                    echo PaymentInformation::getWidgetHtml(
                        paymentId: Metadata::getPaymentId(order: $order)
                    );
                } catch (Throwable $e) {
                    Logger::error(message: $e);
                }
            }
        );
    }

    /**
     * Render payment information box on order view.
     *
     * @deprecated Use inline closure in addPaymentInfo instead
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function renderPaymentInfo(): void
    {
        $order = self::getCurrentOrder();

        try {
            if ($order === null || !Admin::isInShopOrderEdit()) {
                return;
            }

            add_action('admin_footer', static function (): void {
                if (!Admin::isInShopOrderEdit()) {
                    return;
                }

                ?>
              <script type="text/javascript">
                  jQuery(document).ready(function ($) {
                      $('select#_payment_method option:not(:selected)').attr('disabled', true);
                  });
              </script>
                <?php
            });

            $data = PaymentInformation::getWidgetHtml(
                paymentId: Metadata::getPaymentId(order: $order)
            );
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();

            $httpCode = $e->httpCode ?? 0;

            // According to APIs (when we get the codes), code 403 means the payment is either denied due to
            // the credentials or no longer available due to expiration.
            if ($httpCode === 403) {
                $errorMessage = Translator::translate(
                    phraseId: 'payment-info-denied-or-no-longer-available'
                );
            }

            $data = '<b>' .
                Translator::translate(
                    phraseId: 'failed-to-fetch-payment-data-from-the-server'
                ) . ' ' .
                Translator::translate(
                    phraseId: 'reason'
                ) . ':</b> ' . $errorMessage;

            Logger::error(message: $e);
        }

        // Skip sanitizing of data here.
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
     * Get currently viewed WP_Post as WP_Order instance, if any. For example,
     * while on the order view in admin we can obtain the currently viewed order
     * this way.
     *
     * @SuppressWarnings(PHPMD.EmptyCatchBlock)
     */
    public static function getCurrentOrder(): ?WC_Order
    {
        try {
            $currentOrder = wc_get_order();

            if ($currentOrder instanceof WC_Order) {
                return $currentOrder;
            }
        } catch (Throwable) {
            // wc_get_order is a WooCommerce owned method that normally returns false on errors.
            // They should not be necessary to log.
        }

        return null;
    }
}
