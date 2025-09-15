<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement\Action;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\PaymentActionException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Module\Payment\Enum\ActionType;
use Resursbank\Ecom\Module\Payment\Enum\Status;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Database\Options\OrderManagement\EnableModify;
use Resursbank\Woocommerce\Modules\OrderManagement\Action;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Modules\Payment\Converter\Order;
use Resursbank\Woocommerce\Util\Currency;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;
use WC_Abstract_Order;
use WC_Order;

/**
 * Business logic to modify Resurs Bank payment.
 */
class Modify extends Action
{
    /**
     * Used to ensure that we don't make multiple attempts to modify the payment.
     */
    private static bool $hasAlreadyLogged = false;

    /**
     * Marker for modify actions on shutdown, when WP/WC finalized their own internal actions.
     */
    private static bool $execModify = false;

    /**
     * Temporary storage for order, to bring into the final execution.
     */
    private static null|WC_Abstract_Order|WC_Order $order = null;

    /**
     * Modify content of Resurs Bank payment.
     *
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws PaymentActionException
     * @throws ReflectionException
     * @throws Throwable
     * @throws ValidationException
     */
    public static function exec(
        Payment $payment,
        WC_Order $order
    ): void {
        if (
            $payment->status === Status::TASK_REDIRECTION_REQUIRED ||
            !self::validate(payment: $payment, order: $order)
        ) {
            return;
        }

        // Register action once, while we wait for all actions to finalized from WooCommerce.
        // Make sure to only run this, if it is enabled from admin.
        if (EnableModify::isEnabled() && !self::$execModify) {
            /**
             * WARNING! Do not centralize this feature! We don't want to run this randomly, but only in one
             * specific scenario for final order line handling, for where WooCommerce updates orders several
             * times.
             *
             * @see https://resursbankplugins.atlassian.net/browse/WOO-1243
             */
            add_action(
                'shutdown',
                '\Resursbank\Woocommerce\Modules\OrderManagement\Action\Modify::execModify',
                10,
                3
            );
        }

        self::$execModify = true;
        self::$order = $order;
    }

    /**
     * Final execution of modify, after an order has been entirely processed by WooCommerce.
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    // phpcs:ignore
    public static function execModify(): void
    {
        if (!self::$execModify && !is_ajax() || !EnableModify::isEnabled()) {
            return;
        }

        $order = self::$order;
        OrderManagement::execAction(
            action: ActionType::MODIFY_ORDER,
            order: self::$order,
            callback: static function () use ($order): void {
                $payment = OrderManagement::getPayment(
                    order: $order
                );

                $allowModify = self::canBulkModify(
                    order: $order,
                    payment: $payment
                ) || is_ajax();

                // If Resurs payment status is still in redirection, the order can not be cancelled, but for
                // cancels we must allow wooCommerce to cancel orders (especially pending orders), since
                // they tend to disappear if we throw exceptions. However, if orders are already cancelled
                // we should avoid the update.
                if (
                    OrderManagement::$hasActiveCancel ||
                    !$payment->canCancel() ||
                    $payment->status === Status::TASK_REDIRECTION_REQUIRED ||
                    !$allowModify
                ) {
                    return;
                }

                if ($payment->canCancel()) {
                    Repository::cancel(
                        paymentId: $payment->id
                    );
                }

                $orderLines = Order::getOrderLines(order: $order);

                if (
                    count(
                    value: $orderLines
                ) > 0 &&
                    $orderLines->getTotal() > 0
                ) {
                    Repository::addOrderLines(
                        paymentId: $payment->id,
                        orderLines: $orderLines
                    );
                }

                OrderManagement::logSuccessPaymentAction(
                    action: ActionType::MODIFY_ORDER,
                    order: $order,
                    amount: (float)$order->get_total()
                );
            }
        );
    }

    /**
     * Allow to modify as long as order is not in bulk mode and not frozen.
     */
    public static function canBulkModify(WC_Order $order, Payment $payment): bool
    {
        global $resursCheckBulkIds;

        $bulkType = $resursCheckBulkIds[$order->get_id()] ?? '';

        return $bulkType === 'mark_completed' && $payment->canCancel() && !$payment->isFrozen();
    }

    /**
     * Whether requested modify amount is possible against Resurs Bank payment.
     *
     * @throws PaymentActionException
     * @throws Throwable
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    // phpcs:ignore
    private static function validate(
        Payment $payment,
        WC_Order $order
    ): bool {
        $availableAmount = $payment->application->approvedCreditLimit;

        try {
            $requestedAmount = (float)$order->get_total();

            if ($requestedAmount > $availableAmount) {
                throw new PaymentActionException(
                    message: "Requested amount $requestedAmount exceeds $availableAmount on $payment->id"
                );
            }

            return true;
        } catch (Throwable $error) {
            if (!isset($requestedAmount)) {
                throw $error;
            }

            // No need to log failed removals, since nothing will be updated anyway.
            if (
                isset($_REQUEST['action']) &&
                $_REQUEST['action'] !== 'woocommerce_remove_order_item'
            ) {
                self::handleValidationError(
                    error: $error,
                    requestedAmount: (float)$requestedAmount,
                    availableAmount: (float)$availableAmount,
                    order: $order
                );
            }

            return false;
        }
    }

    /**
     * Handle logging of validation errors.
     * @throws \Exception
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    private static function handleValidationError(
        Throwable $error,
        float $requestedAmount,
        float $availableAmount,
        WC_Order $order
    ): void {
        if (self::$hasAlreadyLogged) {
            return;
        }

        OrderManagement::logError(
            sprintf(
                Translator::translate(phraseId: 'modify-too-large'),
                Currency::getFormattedAmount(
                    amount: (float)$requestedAmount
                ),
                Currency::getFormattedAmount(
                    amount: (float)$availableAmount
                )
            ),
            error: $error,
            order: $order
        );
        self::$hasAlreadyLogged = true;
    }
}
