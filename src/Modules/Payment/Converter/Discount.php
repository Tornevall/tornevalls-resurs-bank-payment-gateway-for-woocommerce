<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Payment\Converter;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Locale\Translator;
use Resursbank\Ecom\Lib\Order\OrderLineType;
use Resursbank\Ecom\Lib\Model\Payment\Order\ActionLog\OrderLine;
use Resursbank\Ecom\Lib\Utilities\Tax;
use WC_Coupon;

use function is_bool;
use function is_string;

/**
 * Generic methods to convert WC_Coupon object to OrderLine.
 */
class Discount
{
    /**
     * @param WC_Coupon $coupon
     * @param float $inclTax
     * @param float $exclTax
     * @return OrderLine
     * @throws ConfigException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     */
    public static function toOrderLine(
        WC_Coupon $coupon,
        float $inclTax,
        float $exclTax
    ): OrderLine {
        $code = self::getCode(coupon: $coupon);
        $couponDescription = self::getDescription(coupon: $coupon);

        return new OrderLine(
            quantity: 1,
            quantityUnit: Translator::translate(
                phraseId: 'default-quantity-unit'
            ),
            vatRate: Tax::getRate(taxAmount: ($inclTax - $exclTax), totalInclTax: $inclTax),
            totalAmountIncludingVat: $inclTax,
            description: $couponDescription !== '' ? $couponDescription : $code,
            reference: self::getCode(coupon: $coupon),
            type: OrderLineType::DISCOUNT
        );
    }

    /**
     * @param WC_Coupon $coupon
     * @return string
     * @throws IllegalValueException
     */
    public static function getCode(WC_Coupon $coupon): string
    {
        $result = $coupon->get_code();

        if (!is_string(value: $result)) {
            throw new IllegalValueException(
                message: 'Result from calling "$coupon->get_code()" is not a string.'
            );
        }

        return $result;
    }

    /**
     * @param WC_Coupon $coupon
     * @return string
     * @throws IllegalValueException
     */
    public static function getDescription(WC_Coupon $coupon): string
    {
        $result = $coupon->get_description();

        if (!is_string(value: $result)) {
            throw new IllegalValueException(
                message: 'Result from calling "$coupon->get_description()" is not a string.'
            );
        }

        return $result;
    }

    /**
     * Wrapper to safely check if coupon usage is enabled.
     *
     * @return bool
     * @throws IllegalValueException
     */
    public static function isCouponsEnabled(): bool
    {
        $result = wc_coupons_enabled();

        if (!is_bool(value: $result)) {
            throw new IllegalValueException(
                message: 'Result from calling "wc_coupons_enabled()" is not a bool.'
            );
        }

        return $result;
    }
}
