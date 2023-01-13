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
use Resursbank\Woocommerce\Modules\Payment\Converter\Order\Shipping;
use Resursbank\Woocommerce\Modules\Payment\Converter\Order\Product;
use WC_Order;
use WC_Order_Item_Product;

use function array_merge;
use function is_array;

/**
 * Conversion of WC_Order to OrderLineCollection.
 */
class Order
{
    /**
     * @throws IllegalTypeException
     * @throws Exception
     */
    public static function getOrderLines(WC_Order $order): OrderLineCollection
    {
        $result = [];
        $items = self::getOrderContent(order: $order);
        $collection = new DiscountItemCollection(data: []);

        /** @var WC_Order_Item_Product $productData */
        foreach ($items as $item) {
            // Do not trust anonymous arrays.
            if (!($item instanceof WC_Order_Item_Product)) {
                continue;
            }

            $result[] = Product::toOrderLine(product: $item);

            if (Product::getSubtotal(product: $item) <= Product::getTotal(product: $item)) {
                continue;
            }

            // Total incl. tax before discounts are applied.
            $subtotal = Product::getSubtotal(product: $item);

            // Total incl. tax after discounts have been applied.
            $total = Product::getTotal(product: $item);

            // Similar checks are performed by WC to confirm discount.
            if ($subtotal <= $total) {
                continue;
            }

            // Create new rate group / append amount to existing rate group.
            $collection->addRateData(
                rate: Product::getVatRate(product: $item),
                amount: $subtotal - $total
            );
        }

        $result[] = Shipping::getOrderLine(order: $order);

        return new OrderLineCollection(data: array_merge($result, $collection->getOrderLines()));
    }

    /**
     * Wrapper to safely resolve order content.
     *
     * @return array<array-key, mixed>
     * @throws IllegalValueException
     */
    public static function getOrderContent(WC_Order $order): array
    {
        $result = $order->get_items();

        if (!is_array(value: $result)) {
            throw new IllegalValueException(
                message: 'Failed to resolve items from order.'
            );
        }

        return $result;
    }
}
