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

/**
 * Generic methods to convert shipping data to OrderLine.
 */
class Shipping
{
    /**
     * @param float $total
     * @param float $tax
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
        float $total,
        float $tax
    ): OrderLine {
        return new OrderLine(
            quantity: 1,
            quantityUnit: Translator::translate(
                phraseId: 'default-quantity-unit'
            ),
            vatRate: Tax::getRate(taxAmount: $tax, totalInclTax: $total),
            totalAmountIncludingVat: $total,
            description: Translator::translate(
                phraseId: 'shipping-description'
            ),
            reference: Translator::translate(
                phraseId: 'shipping-reference'
            ),
            type: OrderLineType::SHIPPING
        );
    }
}
