<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\PaymentInformation;

use Automattic\WooCommerce\Admin\PageController;
use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Module\Payment\Widget\PaymentInformation as EcomPaymentInformation;
use Resursbank\Woocommerce\Util\Currency;
use Resursbank\Woocommerce\Util\Sanitize;

/**
 * Handles the output of the order view payment information widget
 *
 * @SuppressWarnings(PHPMD.CamelCaseVariableName)
 */
class PaymentInformation
{
    public EcomPaymentInformation $widget;

    /**
     * @param string $paymentId Resurs payment ID
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws FilesystemException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     */
    public function __construct(string $paymentId)
    {
        $currencySymbol = Currency::getWooCommerceCurrencySymbol();
        $currencyFormat = Currency::getEcomCurrencyFormat();
        $this->widget = new EcomPaymentInformation(
            paymentId: $paymentId,
            currencySymbol: $currencySymbol,
            currencyFormat: $currencyFormat
        );
    }

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
        if ((new PageController())->get_current_screen_id() !== 'shop_order') {
            return;
        }

        echo '<style>' .
            Sanitize::sanitizeHtml(html: self::getCss()) .
            '</style>';
    }

    /**
     * Outputs the actual widget HTML
     *
     * @noinspection PhpUnused
     */
    public function getWidget(): void
    {
        echo $this->widget->content;
    }

    /**
     * Required by css styling for some unknown reason, to make the logo correctly placed.
     */
    private static function getCss(): string
    {
        return ".rb-pi table tr:nth-child(even) {background-color: #006464;}
.rb-pi table tr:nth-child(odd) {background-color: #009b96;}";
    }
}
