<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Order\Filter;

use Exception;
use http\Exception\RuntimeException;
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
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Modules\Order\Order as OrderModule;
use Resursbank\Woocommerce\Modules\Payment\Converter\Order;
use Resursbank\Woocommerce\Util\Database;
use Resursbank\Woocommerce\Util\Metadata;
use Throwable;
use WC_Order;

/**
 * Event executed when order item is deleted.
 */
class DeleteItem
{
//    private static WC_Order|null $order;

    /**
     * Register action filter which executed when order item gets deleted.
     *
     * @throws ConfigException
     */
    public static function register(): void
    {
        add_action(
            hook_name: 'woocommerce_before_delete_order_item',
            callback: static function (mixed $itemId) {
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

        Repository::cancel(
            paymentId: OrderModule::getPaymentId(order: $order),
            orderLines: Order::getOrderLines(
                order: $order,
                filter: [$itemId],
                includeShipping: false
            )
        );
    }
}
