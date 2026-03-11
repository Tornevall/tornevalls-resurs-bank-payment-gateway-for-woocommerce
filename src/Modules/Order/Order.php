<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Order;

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
use Resursbank\Woocommerce\Modules\PaymentInformation\PaymentInformation;
use Resursbank\Woocommerce\Util\Admin;
use Resursbank\Woocommerce\Util\HtmlSanitizer;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Translator;
use Resursbank\Woocommerce\Util\Url;
use Throwable;
use WC_Order;

use const RESURSBANKABPAYMENTS_MODULE_PREFIX;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

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
   * Add JavaScript to order view to update content when order is updated.
   */
    public static function initAdmin(): void
    {
        add_action(
            'admin_enqueue_scripts',
            'Resursbank\Woocommerce\Modules\Order\Order::initAdminScripts'
        );
    }

  /**
   * @SuppressWarnings(PHPMD.Superglobals)
   * @noinspection PhpArgumentWithoutNamedIdentifierInspection
   */
    public static function initAdminScripts(): void
    {
        try {
          // Fetching the order id this way has historically been the best way on
          // sites where the normal way of doing it not works ("ecompress"). This however fails
          // when in HPOS-mode. If the solution below does not work, then we have to
          // reconsider the way this has been historically done,
            $wcOrder = wc_get_order();

            if (
                !$wcOrder instanceof WC_Order ||
                !Metadata::isValidResursPayment(order: $wcOrder)
            ) {
                return;
            }

            $wcOrderid = $wcOrder->get_id();
            $fetchUrl = Route::getUrl(
                route: Route::ROUTE_ADMIN_GET_ORDER_CONTENT,
                admin: true
            );

            $url = Url::getResourceUrl(
                module: 'Order',
                file: 'admin/getOrderContent.js'
            );

            wp_enqueue_script(
                'resursbankabpaygw-get-order-content-admin-scripts',
                $url,
                ['jquery'],
                '1.0.0',
                true
            );

            wp_register_script(
                'resursbankabpaygw-get-order-content-admin-inline-scripts',
                '',
                ['resursbankabpaygw-get-order-content-admin-scripts'],
                '1.0.0',
                true
            );
            wp_enqueue_script(
                'resursbankabpaygw-get-order-content-admin-inline-scripts'
            );
            wp_add_inline_script(
                'resursbankabpaygw-get-order-content-admin-inline-scripts',
                sprintf(
                    'RESURSBANK_GET_ORDER_CONTENT(%s, %s);',
                    (string)wp_json_encode(
                        $fetchUrl,
                        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                    ),
                    (string)wp_json_encode(
                        (string)$wcOrderid,
                        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                    )
                )
            );
        } catch (Throwable $error) {
            Log::error(error: $error);
        }
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
            'Resursbank\Woocommerce\Modules\Order\Order::renderPaymentInfo'
        );
    }

  /**
   * Render payment information box on order view.
   *
   * NOTE:
   * - The HTML comes from an SDK.
   * - wp_kses() is fine, but you must allow the specific inline CSS properties used by the SDK.
   *   Otherwise, "style=\"display: none;\"" is stripped and loader/overlay/error become visible.
   *
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

            Log::error(error: $e);
        }

      // Allow only the minimal CSS properties needed by the SDK markup.
      // This is applied only for this rendering, then removed.
        $styleFilter = static function (array $styles): array {
            $styles[] = 'display';

          // The SDK logo uses inline SVG path styles in some builds.
          // Keeping these allows the SVG to render identically pre/post kses.
            $styles[] = 'fill';
            $styles[] = 'fill-opacity';
            $styles[] = 'fill-rule';
            $styles[] = 'stroke';
            $styles[] = 'stroke-width';
            $styles[] = 'stroke-miterlimit';

            return array_values(array_unique($styles));
        };

        add_filter('safe_style_css', $styleFilter);

        echo wp_kses(
            (string)$data,
            HtmlSanitizer::getPaymentInfoAllowlist()
        );

        remove_filter('safe_style_css', $styleFilter);
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
                needle: RESURSBANKABPAYMENTS_MODULE_PREFIX . '_'
            )
        ) {
            return true;
        }

        return $protected;
    }

  /**
   * @throws ApiException
   * @throws AuthException
   * @throws CacheException
   * @throws ConfigException
   * @throws CurlException
   * @throws EmptyValueException
   * @throws IllegalTypeException
   * @throws IllegalValueException
   * @throws JsonException
   * @throws ReflectionException
   * @throws Throwable
   * @throws ValidationException
   */
    public static function getPaymentMethod(WC_Order $order): ?PaymentMethod
    {
        $method = (string)$order->get_payment_method();

        if ($method === '') {
            return null;
        }

        return Repository::getById(paymentMethodId: $method);
    }

  /**
   * Get currently viewed WP_Post as WP_Order instance, if any.
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
          // WooCommerce owned method that normally returns false on errors.
        }

        return null;
    }
}
