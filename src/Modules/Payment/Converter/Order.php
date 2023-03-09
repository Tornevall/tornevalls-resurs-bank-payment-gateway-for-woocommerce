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
use WC_Order_Refund;

use function array_merge;
use function is_array;

/**
 * Conversion of WC_Order or WC_Order_Refund to OrderLineCollection.
 */
class Order
{
    /**
     * @throws IllegalTypeException
     * @throws Exception
     */
    public static function getOrderLines(
        WC_Order|WC_Order_Refund $order
    ): OrderLineCollection {
        $result = [];
        $items = self::getOrderContent(order: $order);
        $discountCollection = new DiscountItemCollection(data: []);

        /** @var WC_Order_Item_Product $item */
        foreach ($items as $item) {
            // Do not trust anonymous arrays.
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }

            self::addDiscountData(item: $item, collection: $discountCollection);

            $result[] = Product::toOrderLine(product: $item);
        }

        // When we filter specific items we do not want to include this.
        if (Shipping::isAvailable(order: $order)) {
            $result[] = Shipping::getOrderLine(order: $order);
        }

        return new OrderLineCollection(
            data: array_merge(
                $result,
                $discountCollection->getOrderLines()->toArray()
            )
        );
    }

    /**
     * Convert string (expected) to a float value with a precision of two. Also
     * ensure that we only return positive values, WC_Order_Refund will list
     * negative values, our API expects positives.
     *
     * @throws IllegalValueException
     */
    public static function convertFloat(
        mixed $value
    ): float {
        if (!is_numeric(value: $value)) {
            throw new IllegalValueException(
                message: 'Cannot convert none numeric value.'
            );
        }

        return round(num: abs(num: (float) $value), precision: 2);
    }

    /**
     * @throws IllegalTypeException
     * @throws IllegalValueException
     */
    private static function addDiscountData(
        WC_Order_Item_Product $item,
        DiscountItemCollection $collection
    ): void {
        // Total incl. tax before discounts are applied.
        $subtotal = Product::getSubtotal(product: $item);

        // Total incl. tax after discounts have been applied.
        $total = self::convertFloat(value: $item->get_total());

        // VAT amounts for subtotal and total.
        $subtotalVat = Product::getSubtotalVat(product: $item);
        $totalVat = self::convertFloat(value: $item->get_total_tax());

        // Similar checks are performed by WC to confirm discount.
        if ($subtotal <= $total) {
            return;
        }

        // Create new rate group / append amount to existing rate group.
        $collection->addRateData(
            rate: Product::getVatRate(product: $item),
            amount: $subtotal + $subtotalVat - $total - $totalVat
        );
    }

    /**
     * Wrapper to safely resolve order content.
     *
     * @return array<array-key, mixed>
     * @throws IllegalValueException
     */
    private static function getOrderContent(
        WC_Order|WC_Order_Refund $order
    ): array {
        $result = $order->get_items();

        if (!is_array(value: $result)) {
            throw new IllegalValueException(
                message: 'Failed to resolve items from order.'
            );
        }

        return $result;
    }
}
