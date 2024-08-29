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
use Resursbank\Ecom\Exception\AttributeCombinationException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\Validation\NotJsonEncodedException;
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
 * @phpcsSuppress SlevomatCodingStandard.Classes.ClassLength
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.LongVariable)
 * @noinspection EfferentObjectCouplingInspection
 */
class OrderManagement
{
    /**
     * Race conditional stored payment.
     */
    public static bool $hasActiveCancel = false;

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
            10,
            3
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
        // Try to put us last in the reply chain so our answer is the last to make the decision.
        add_filter(
            'wc_order_is_editable',
            'Resursbank\Woocommerce\Modules\OrderManagement\Filter\IsOrderEditable::exec',
            9999,
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

        // Hide capture action on order list view.
        add_filter(
            'woocommerce_admin_order_actions',
            'Resursbank\Woocommerce\Modules\OrderManagement\Filter\HideCaptureAction::exec',
            999,
            2
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
     * @throws AttributeCombinationException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws NotJsonEncodedException
     * @throws ReflectionException
     * @throws ValidationException
     */
    public static function canEdit(WC_Order $order): bool
    {
        self::getCanNotEditTranslation(order: $order);

        $frozenOrRejected = (self::isFrozen(order: $order) || self::isRejected(
            order: $order
        ));
        $payment = self::getPayment(order: $order);

        return
            !$frozenOrRejected &&
            (
                self::canCapture(order: $order) ||
                self::canCancel(order: $order) ||
                (
                    $payment->isCancelled() &&
                    $payment->application->approvedCreditLimit > 0.0
                )
            );
    }

    /**
     * Update translation in WooCommerce at editor level if Resurs has an order frozen or rejected.
     *
     * @throws ApiException
     * @throws AttributeCombinationException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws NotJsonEncodedException
     * @throws ReflectionException
     * @throws ValidationException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public static function getCanNotEditTranslation(WC_Order $order): void
    {
        $isFrozen = self::isFrozen(order: $order);
        $isRejected = self::isRejected(order: $order);

        // Skip translation filter if not frozen nor rejected.
        if (!$isRejected && !$isFrozen) {
            return;
        }

        /**
         * @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter
         * @phpcs:ignoreFile CognitiveComplexity
         */
        add_filter(
            'gettext',
            static function ($translation, $text, $domain) use ($isFrozen, $isRejected) {
                if (
                    isset($text) &&
                    is_string(
                        value: $text
                    ) &&
                    $text === 'This order is no longer editable.'
                ) {
                    if ($isRejected) {
                        $translation = Translator::translate(
                            phraseId: 'can-not-edit-order-due-to-rejected'
                        );
                    }

                    if ($isFrozen) {
                        $translation = Translator::translate(
                            phraseId: 'can-not-edit-order-due-to-frozen'
                        );
                    }
                }

                return $translation;
            },
            999,
            3
        );
    }

    /**
     * Check if order is FROZEN.
     *
     * @throws ApiException
     * @throws AttributeCombinationException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws NotJsonEncodedException
     * @throws ReflectionException
     * @throws ValidationException
     */
    public static function isFrozen(WC_Order $order): bool
    {
        $payment = self::getPayment(order: $order);
        return $payment->isFrozen();
    }

    /**
     * Is order rejected?
     *
     * @throws ApiException
     * @throws AttributeCombinationException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws NotJsonEncodedException
     * @throws ReflectionException
     * @throws ValidationException
     */
    public static function isRejected(WC_Order $order): bool
    {
        $payment = self::getPayment(order: $order);
        return $payment->isRejected();
    }

    /**
     * @throws ApiException
     * @throws AttributeCombinationException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws NotJsonEncodedException
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
     * @throws AttributeCombinationException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws NotJsonEncodedException
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
     * @throws AttributeCombinationException
     * @throws AuthException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws NotJsonEncodedException
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
            /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
            $result = new WC_Order($id);

            if (!$result instanceof WC_Order) {
                $result = null;

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
     * @throws AttributeCombinationException
     * @throws NotJsonEncodedException
     */
    public static function getPayment(WC_Order $order): Payment
    {
        $id = (int)$order->get_id();

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
        $order->add_order_note(note: $message);
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
            message: sprintf(
                Translator::translate(phraseId: "$actionStr-success"),
                Currency::getFormattedAmount(amount: (float)$amount)
            ),
            order: $order
        );
    }
}
