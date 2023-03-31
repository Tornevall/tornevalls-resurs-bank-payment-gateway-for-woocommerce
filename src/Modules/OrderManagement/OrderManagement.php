<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Api\Environment as EnvironmentEnum;
use Resursbank\Ecom\Lib\Api\MerchantPortal;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Module\Payment\Enum\ActionType;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Database\Options\Api\Enabled;
use Resursbank\Woocommerce\Database\Options\Api\Environment;
use Resursbank\Woocommerce\Database\Options\OrderManagement\EnableModify;
use Resursbank\Woocommerce\Database\Options\OrderManagement\EnableRefund;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Util\Currency;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;
use WC_Order;

/**
 * Business logic relating to order management functionality.
 *
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @noinspection EfferentObjectCouplingInspection
 */
class OrderManagement
{
    /**
     * Track resolved payments to avoid additional API calls.
     */
    private static array $payments = [];

    /**
     * The actual method that sets up actions for order status change hooks.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function init(): void
    {
        if (!Enabled::isEnabled()) {
            return;
        }

        self::initRefund();
        self::initModify();

        // Break status update if unavailable based on payment status.
        add_action(
            'transition_post_status',
            'Resursbank\Woocommerce\Modules\OrderManagement\Filter\BeforeOrderStatusChange::exec',
            10,
            3
        );

        // Execute payment action AFTER status has changed in WC.
        add_action(
            'woocommerce_order_status_changed',
            'Resursbank\Woocommerce\Modules\OrderManagement\Filter\AfterOrderStatusChange::exec',
            3,
            10
        );

        // Add custom CSS rules relating to order view.
        add_action(
            'admin_head',
            'Resursbank\Woocommerce\Modules\OrderManagement\Filter\DisableDeleteRefund::exec'
        );
    }

    /**
     * Register modification related event listeners.
     */
    public static function initModify(): void
    {
        if (!EnableModify::isEnabled()) {
            return;
        }

        // Prevent order edit options from rendering if we can't modify payment.
        add_filter(
            'wc_order_is_editable',
            'Resursbank\Woocommerce\Modules\OrderManagement\Filter\IsOrderEditable::exec',
            10,
            2
        );

        // Perform payment action to update payment when order content changes.
        add_action(
            'woocommerce_update_order',
            'Resursbank\Woocommerce\Modules\OrderManagement\Filter\UpdateOrder::exec',
            10,
            2
        );
    }

    /**
     * Register refund related event listeners.
     */
    public static function initRefund(): void
    {
        if (!EnableRefund::isEnabled()) {
            return;
        }

        // Prevent order refund options from rendering when unavailable.
        add_filter(
            'woocommerce_admin_order_should_render_refunds',
            'Resursbank\Woocommerce\Modules\OrderManagement\Filter\IsOrderRefundable::exec',
            10,
            3
        );

        // Execute refund payment action after refund has been created.
        add_action(
            'woocommerce_order_refunded',
            'Resursbank\Woocommerce\Modules\OrderManagement\Filter\Refund::exec',
            10,
            2
        );

        // Prevent internal note indicating funds need to be manually returned.
        add_filter(
            'woocommerce_new_order_note_data',
            'Resursbank\Woocommerce\Modules\OrderManagement\Filter\DisableRefundNote::exec',
            10,
            1
        );
    }

    /**
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ValidationException
     */
    public static function canEdit(WC_Order $order): bool
    {
        $payment = self::getPayment(order: $order);

        return
            self::canCapture(order: $order) ||
            self::canCancel(order: $order) ||
            (
                $payment->isCancelled() &&
                $payment->application->approvedCreditLimit > 0.0
            )
        ;
    }

    /**
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ValidationException
     */
    public static function canCapture(WC_Order $order): bool
    {
        $payment = self::getPayment(order: $order);

        return $payment->canCapture() || $payment->canPartiallyCapture();
    }

    /**
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ValidationException
     */
    public static function canRefund(WC_Order $order): bool
    {
        $payment = self::getPayment(order: $order);

        return $payment->canRefund() || $payment->canPartiallyRefund();
    }

    /**
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ValidationException
     */
    public static function canCancel(
        WC_Order $order
    ): bool {
        $payment = self::getPayment(order: $order);

        return $payment->canCancel() || $payment->canPartiallyCancel();
    }

    /**
     * Get WC_Order from id.
     */
    public static function getOrder(int $id): ?WC_Order
    {
        $result = null;

        try {
            $result = wc_get_order(the_order: $id);

            if (!$result instanceof WC_Order) {
                throw new IllegalTypeException(
                    message: 'Returned object not of type WC_Order'
                );
            }
        } catch (Throwable $error) {
            Log::error(
                error: $error,
                message: sprintf(
                    Translator::translate(phraseId: 'failed-resolving-order'),
                    $id
                )
            );
        }

        return $result;
    }

    /**
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws IllegalValueException
     */
    public static function getPayment(WC_Order $order): Payment
    {
        $id = (int) $order->get_id();

        if (isset(self::$payments[$id])) {
            return self::$payments[$id];
        }

        $result = Repository::get(
            paymentId: Metadata::getPaymentId(order: $order)
        );

        self::$payments[$id] = $result;

        return $result;
    }

    /**
     * Add error message to order notes and message bag.
     */
    public static function logError(
        string $message,
        Throwable $error,
        ?WC_Order $order = null
    ): void {
        Log::error(error: $error);
        MessageBag::addError(message: $message);

        if ($order === null) {
            return;
        }

        $url = Environment::getData() === EnvironmentEnum::PROD ?
            MerchantPortal::PROD :
            MerchantPortal::TEST;

        $message .= ' <a href="' . $url->value . '" target="_blank">Merchant Portal</a>';
        $order->add_order_note($message);
    }

    /**
     * Centralized method to execute a payment action and log potential errors.
     */
    public static function execAction(
        ActionType $action,
        WC_Order $order,
        callable $callback
    ): void {
        try {
            $callback();
        } catch (CurlException $error) {
            $trace = $error->getError();

            self::logActionError(
                action: $action,
                order: $order,
                error: $error,
                reason: $trace?->message ?? 'unknown reason'
            );
        } catch (Throwable $error) {
            self::logActionError(action: $action, order: $order, error: $error);
        }
    }

    /**
     * Log error from a Payment Action request (cancel, debit, credit, modify).
     */
    public static function logActionError(
        ActionType $action,
        WC_Order $order,
        Throwable $error,
        string $reason = 'unknown reason'
    ): void {
        $actionStr = str_replace(
            search: '_',
            replace: '-',
            subject: strtolower(string: $action->value)
        );

        self::logError(
            message: sprintf(
                Translator::translate(phraseId: "$actionStr-action-failed"),
                strtolower(string: $reason)
            ),
            error: $error,
            order: $order
        );
    }

    /**
     * Add success message to order notes and message bag.
     */
    public static function logSuccess(
        string $message,
        ?WC_Order $order = null
    ): void {
        Log::debug(message: $message);
        MessageBag::addSuccess(message: $message);
        $order?->add_order_note(note: $message);
    }

    /**
     * Log generic success message from payment action.
     */
    public static function logSuccessPaymentAction(
        ActionType $action,
        WC_Order $order,
        ?float $amount = null
    ): void {
        $actionStr = str_replace(
            search: '_',
            replace: '-',
            subject: strtolower(string: $action->value)
        );

        self::logSuccess(
            order: $order,
            message: sprintf(
                Translator::translate(phraseId: "$actionStr-success"),
                Currency::getFormattedAmount(amount: (float) $amount)
            )
        );
    }
}
