<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Order\Filter;

use Exception;
use JsonException;
use ReflectionException;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Modules\Order\Order as OrderModule;
use Resursbank\Woocommerce\Modules\Payment\Converter\Order;
use Throwable;
use WC_Order;
use WC_Order_Item_Shipping;

/**
 * Event executed when order item is deleted.
 */
class DeleteItem
{
    /**
     * Register action filter which executed when order item gets deleted.
     *
     * @throws ConfigException
     */
    public static function register(): void
    {
        add_action(
            hook_name: 'woocommerce_before_delete_order_item',
            callback: static function (mixed $itemId): void {
                // @todo Error handling. The AJAX request does not respect Exception, it just dies.
                try {
                    self::exec(itemId: (int) $itemId);
                } catch (Throwable $e) {
                    Config::getLogger()->error(message: $e);
                }
            }
        );
    }

    /**
     * Find out if item is of type shipping.
     */
    private static function isShipping(WC_Order $order, int $itemId): bool
    {
        $shippingItems = $order->get_items(types: 'shipping');

        if (is_array(value: $shippingItems)) {
            foreach ($shippingItems as $shippingItem) {
                if (!($shippingItem instanceof WC_Order_Item_Shipping)) {
                    continue;
                }

                if ($shippingItem->get_id() === $itemId) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Cancel item on Resurs Bank payment.
     *
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws Exception
     */
    private static function exec(int $itemId): void
    {
        $order = OrderModule::getOrder(itemId: $itemId);

        if ($order === null) {
            return;
        }

        $isShipping = self::isShipping(order: $order, itemId: $itemId);
        $orderLineCollection = Order::getOrderLines(
            order: $order,
            filter: [$itemId],
            includeShipping: $isShipping
        );

        if (!$orderLineCollection->count()) {
            return;
        }

        Repository::cancel(
            paymentId: OrderModule::getPaymentId(order: $order),
            orderLines: $orderLineCollection
        );
    }
}