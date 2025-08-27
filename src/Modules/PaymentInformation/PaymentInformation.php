<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\PaymentInformation;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AttributeCombinationException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Module\Widget\PaymentInformation\Css as EcomPaymentInformationCss;
use Resursbank\Ecom\Module\Widget\PaymentInformation\Html as EcomPaymentInformation;
use Resursbank\Woocommerce\Util\Admin;
use Resursbank\Woocommerce\Util\Sanitize;

/**
 * Handles the output of the order view payment information widget
 *
 * @SuppressWarnings(PHPMD.CamelCaseVariableName)
 */
class PaymentInformation
{
    /**
     * Init method for loading module CSS.
     */
    public static function init(): void
    {
        add_action(
            'admin_head',
            'Resursbank\Woocommerce\Modules\PaymentInformation\PaymentInformation::setCss'
        );
    }

    /**
     * Sets CSS in header if the current page is the order view.
     */
    public static function setCss(): void
    {
        if (!Admin::isInShopOrderEdit()) {
            return;
        }

        echo '<style>' .
            Sanitize::sanitizeHtml(
                html: (new EcomPaymentInformationCss())->content
            ) .
            '</style>';
    }

    /**
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AttributeCombinationException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws FilesystemException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     */
    public static function getWidgetHtml(string $paymentId): string
    {
        return Sanitize::sanitizeHtml(
            html: (new EcomPaymentInformation(paymentId: $paymentId))->content
        );
    }
}
