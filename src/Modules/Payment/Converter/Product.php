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
use WC_Product;
use WC_Tax;

use function array_shift;
use function is_array;
use function is_float;
use function is_string;

/**
 * Basic methods to convert WC_Product to OrderLine.
 */
class Product
{
    /**
     * @param WC_Product $product
     * @param float $qty
     * @param OrderLineType|null $orderLineType
     * @return OrderLine
     * @throws ConfigException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     * @throws IllegalValueException
     */
    public static function toOrderLine(
        WC_Product $product,
        float $qty,
        OrderLineType $orderLineType = null,
    ): OrderLine {
        return new OrderLine(
            quantity: $qty,
            quantityUnit: self::getQuantityUnit(),
            vatRate: self::getProductVatRate(product: $product),
            totalAmountIncludingVat: self::getTotalAmountIncludingVat(
                product: $product,
                qty: $qty
            ),
            description: self::getTitle(product: $product),
            reference: self::getSku(product: $product),
            type: $orderLineType
        );
    }

    /**
     * @param WC_Product $product
     * @param array $args
     * @return float
     * @throws IllegalValueException
     */
    public static function getPriceIncludingTax(
        WC_Product $product,
        array $args = []
    ): float {
        $result = wc_get_price_including_tax(product: $product, args: $args);

        if (!is_float(value: $result)) {
            throw new IllegalValueException(
                message: 'Result from calling "wc_get_price_including_tax()" is not a float.'
            );
        }

        return $result;
    }

    /**
     * @param WC_Product $product
     * @return string
     * @throws IllegalValueException
     */
    public static function getTitle(WC_Product $product): string
    {
        $result = $product->get_title();

        if (!is_string(value: $result)) {
            throw new IllegalValueException(
                message: 'Result from calling "$product->get_title()" is not a string.'
            );
        }

        return $result;
    }

    /**
     * @param WC_Product $product
     * @return string
     * @throws IllegalValueException
     */
    public static function getSku(WC_Product $product): string
    {
        $result = $product->get_sku();

        if (!is_string(value: $result)) {
            throw new IllegalValueException(
                message: 'Result from calling "$product->get_sku()" is not a string.'
            );
        }

        return $result;
    }

    /**
     * @param WC_Product $product
     * @return string
     * @throws IllegalValueException
     */
    public static function getTaxClass(WC_Product $product): string
    {
        $result = $product->get_tax_class();

        if (!is_string(value: $result)) {
            throw new IllegalValueException(
                message: 'Result from calling "$product->get_tax_class()" is not a string.'
            );
        }

        return $result;
    }

    /**
     * @param string $taxClass
     * @return array
     * @throws IllegalValueException
     */
    public static function getTaxRates(string $taxClass): array
    {
        $result = WC_Tax::get_rates(tax_class: $taxClass);

        if (!is_array(value: $result)) {
            throw new IllegalValueException(
                message: 'Result from calling "WC_Tax::get_rates" is not an array.'
            );
        }

        return $result;
    }

    /**
     * @param WC_Product $product
     * @return float
     * @throws IllegalValueException
     */
    public static function getProductVatRate(WC_Product $product): float
    {
        $taxClass = self::getTaxClass(product: $product);
        $ratesArray = self::getTaxRates(taxClass: $taxClass);
        /**
         * @noinspection PhpArgumentWithoutNamedIdentifierInspection
         * @psalm-suppress MixedAssignment
         */
        $rates = array_shift($ratesArray);

        return (
            is_array(value: $rates) &&
            isset($rates['rate'])
        ) ? (float) $rates['rate'] : 0.0;
    }

    /**
     * @param WC_Product $product
     * @param float $qty
     * @return float
     * @throws IllegalValueException
     */
    public static function getTotalAmountIncludingVat(
        WC_Product $product,
        float $qty
    ): float {
        return self::getPriceIncludingTax(
            product: $product,
            args: ['qty' => $qty]
        );
    }

    /**
     * @return string
     * @throws ConfigException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     * @todo Get the default quantity from somewhere else, like if there's an
     *      option in WordPress for that.
     */
    public static function getQuantityUnit(): string
    {
        // Using default measure from ECom for now.
        return Translator::translate(
            phraseId: 'default-quantity-unit'
        );
    }
}
