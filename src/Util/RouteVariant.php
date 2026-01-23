<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

/**
 * Enum representing available route variants.
 */
enum RouteVariant: string
{
    /**
     * Route to get address controller.
     */
    case GetAddress = 'get-address';

    /**
     * Route to controller injecting get address css.
     */
    case GetAddressCss = 'get-address-css';

    /**
     * Controller route to render get address JS.
     */
    case GetAddressJs = 'get-address-js';

    /**
     * Controller route to render payment method JS.
     */
    case PaymentMethodJs = 'payment-method-js';

    /**
     * Route to get part payment controller.
     */
    case PartPayment = 'part-payment';

    /**
     * Route to get part payment admin controller.
     */
    case PartPaymentAdmin = 'part-payment-admin';

    /**
     * Route to admin cache invalidate controller.
     */
    case AdminCacheInvalidate = 'admin-cache-invalidate';

    /**
     * Route to admin controller which triggers test callback.
     */
    case AdminTriggerTestCallback = 'admin-trigger-test-callback';

    /**
     * Route to controller accepting test callback from Resurs Bank.
     */
    case TestCallbackReceived = 'test-callback-received';

    /**
     * Route to get the test callback received at timestamp.
     */
    case GetCallbackTestReceivedAt = 'get-callback-test-received-at';

    /**
     * Route to get JSON encoded list of stores (only in admin).
     */
    case GetStoresAdmin = 'get-stores-admin';

    /**
     * Route to get updated cost list HTML.
     */
    case Costlist = 'get-costlist';

    /**
     * Route to get JSON encoded order view content.
     */
    case AdminGetOrderContent = 'get-order-content-admin';

    /**
     * Route to authorization callback.
     */
    case AuthorizationCallback = 'authorization-callback';

    /**
     * Route to render admin CSS resources.
     */
    case AdminCss = 'admin-css';

    /**
     * Route to render admin JS resources.
     */
    case AdminJs = 'admin-js';

    /**
     * URL to reload payment information widget.
     */
    case ReloadPaymentInformation = 'reload-payment-information-js';

    /**
     * Route to controller injecting read more CSS.
     */
    case ReadMoreCss = 'read-more-css';

    /**
     * Controller route to render reader more JS.
     */
    case ReadMoreJs = 'read-more-js';

    /**
     * Route to controller injecting part payment CSS.
     */
    case PartPaymentCss = 'part-payment-css';

    /**
     * Controller route to render part payment JS.
     */
    case PartPaymentJs = 'part-payment-js';

    /**
     * Check if the route intends to load a CSS file.
     *
     * @return bool
     */
    public function isCssRoute(): bool
    {
        return in_array(
            $this,
            [
                self::GetAddressCss,
                self::ReadMoreCss,
                self::PartPaymentCss,
                self::AdminCss,
            ],
            true
        );
    }

    /**
     * Check if route intends to load a JS file.
     *
     * @return bool
     */
    public function isJsRoute(): bool
    {
        return in_array(
            $this,
            [
                self::GetAddressJs,
                self::ReadMoreJs,
                self::PartPaymentJs,
                self::AdminJs,
                self::ReloadPaymentInformation,
                self::PaymentMethodJs,
            ],
            true
        );
    }

    public function isAdminRoute(): bool
    {
        return in_array(
            $this,
            [
                self::AdminCss,
                self::AdminJs,
            ],
            true
        );
    }
}
