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
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Module\Payment\Widget\PaymentInformation;
use ResursBank\Module\Data;

/**
 * Handles the output of the order view payment information widget
 */
class Module
{
    /** @var PaymentInformation */
    public PaymentInformation $widget;

    /**
     * @param string $paymentId Resurs payment ID
     *
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
        $this->widget = new PaymentInformation(paymentId: $paymentId);
    }

    /**
     * Outputs the actual widget HTML
     */
    public function getWidget(): void
    {
        echo Data::getEscapedHtml($this->widget->content);
    }

    /**
     * Sets CSS in header if the current page is the order view.
     */
    public static function setCss(): void
    {
        $screen = get_current_screen();
        $screen_id = $screen ? $screen->id : '';

        if ($screen_id === 'shop_order') {
            echo '<style>' . Data::getEscapedHtml(PaymentInformation::getCss()) . '</style>';
        }
    }
}
