<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

/** @noinspection LongInheritanceChainInspection */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Payment\Converter;

use Exception;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Payment\Converter\DiscountItemCollection;
use Resursbank\Ecom\Lib\Model\Payment\Order\ActionLog\OrderLineCollection;
use Resursbank\Woocommerce\Modules\Payment\Converter\Order\Product;
use Resursbank\Woocommerce\Modules\Payment\Converter\Order\Shipping;
use WC_Order;
use WC_Order_Item_Product;

use function array_merge;
use function in_array;
use function is_array;

/**
 * Conversion of WC_Order to OrderLineCollection.
 */
class Order
{
    /**
     * @param $filter | List of items ids to include. Empty = include all.
     * @throws IllegalTypeException
     * @throws Exception
     */
    public static function getOrderLines(
        WC_Order $order,
        array $filter = [],
        bool $includeShipping = true
    ): OrderLineCollection {
        $result = [];
        $items = self::getOrderContent(order: $order);
        $collection = new DiscountItemCollection(data: []);

        /** @var WC_Order_Item_Product $item */
        foreach ($items as $item) {
            // Do not trust anonymous arrays.
            if (!self::validateItem(item: $item, filter: $filter)) {
                continue;
            }

            $result[] = Product::toOrderLine(product: $item);

            // Total incl. tax before discounts are applied.
            $subtotal = Product::getSubtotal(product: $item);

            // Total incl. tax after discounts have been applied.
            $total = Product::getTotal(product: $item);

            // VAT amounts for subtotal and total.
            $subtotalVat = Product::getSubtotalVat(product: $item);
            $totalVat = Product::getTotalVat(product: $item);

            // Similar checks are performed by WC to confirm discount.
            if ($subtotal <= $total) {
                continue;
            }

            // Create new rate group / append amount to existing rate group.
            $collection->addRateData(
                rate: Product::getVatRate(product: $item),
                amount: $subtotal + $subtotalVat - $total - $totalVat
            );
        }

        // When we filter specific items we do not want to include this.
        if ($includeShipping) {
            $result[] = Shipping::getOrderLine(order: $order);
        }

        return new OrderLineCollection(
            data: array_merge($result, $collection->getOrderLines()->toArray())
        );
    }

    /**
     * Wrapper to safely resolve order content.
     *
     * @return array<array-key, mixed>
     * @throws IllegalValueException
     */
    private static function getOrderContent(WC_Order $order): array
    {
        $result = $order->get_items();

        if (!is_array(value: $result)) {
            throw new IllegalValueException(
                message: 'Failed to resolve items from order.'
            );
        }

        return $result;
    }

    /**
     * Check if item can be part of outgoing API payload.
     */
    private static function validateItem(mixed $item, array $filter): bool
    {
        return 
            $item instanceof WC_Order_Item_Product &&
            (
                empty($filter) ||
                in_array(
                    needle: (int) $item->get_id(),
                    haystack: $filter,
                    strict: true
                )
            )
        ;
    }
}
