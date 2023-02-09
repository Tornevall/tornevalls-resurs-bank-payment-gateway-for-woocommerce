<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Order;

use Exception;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Module\PaymentMethod\Enum\CurrencyFormat;
use Resursbank\Woocommerce\Modules\Order\Filter\DeleteItem;
use Resursbank\Woocommerce\Util\Currency;
use Resursbank\Woocommerce\Util\Metadata;
use Throwable;
use WC_Order;

/**
 * Part payment widget.
 */
class Order
{
    /**
     * Initialize Order module.
     */
    public static function init(): void
    {
        DeleteItem::register();
    }

    /**
     * Resolve order matching item id if it exists, implements WC_Order, and
     * contains metadata indicating it was purchased through Resurs Bank.
     *
     * @return WC_Order|null WC_Order if paid using Resurs Bank.
     * @throws Exception
     */
    public static function getOrder(int $itemId): ?WC_Order
    {
        $order = null;

        try {
            $order = wc_get_order(
                the_order: wc_get_order_id_by_order_item_id(item_id: $itemId)
            );
        } catch (Throwable) {
            // Do nothing. Next if-statement will exit anyway.
        }

        return (
            $order instanceof WC_Order &&
            Metadata::isValidResursPayment(order:$order)
        ) ? $order : null;
    }

    /**
     * Get Resurs Bank payment id attached to order.
     *
     * @throws IllegalValueException
     */
    public static function getPaymentId(WC_Order $order): string
    {
        $id = Metadata::getPaymentId(order: $order);

        if ($id === '') {
            throw new IllegalValueException(
                message: 'Missing Resurs Bank payment UUID.'
            );
        }

        return $id;
    }

    /**
     * Return amounts with proper currency.
     *
     * @todo Centralize and let PartPayment use this display too. See WOO-1067.
     */
    public static function getFormattedAmount(float $amount): string
    {
        $currencySymbol = Currency::getWooCommerceCurrencySymbol();
        $currencyFormat = CurrencyFormat::SYMBOL_FIRST ?
            preg_match(
                pattern: '/\%1\$s.*\%2\$s/',
                subject: Currency::getWooCommerceCurrencyFormat()
            ) : CurrencyFormat::SYMBOL_LAST;

        return $currencyFormat === CurrencyFormat::SYMBOL_FIRST ?
            $currencySymbol . ' ' . $amount : $amount . ' ' . $currencySymbol;
    }
}
