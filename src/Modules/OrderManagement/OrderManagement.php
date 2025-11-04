<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\OrderManagement;

use Exception;
use JsonException;
use ReflectionException;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AttributeCombinationException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\UserSettingsException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\Validation\NotJsonEncodedException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Api\Environment as EnvironmentEnum;
use Resursbank\Ecom\Lib\Api\MerchantPortal;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Lib\UserSettings\Field;
use Resursbank\Ecom\Lib\Utilities\Price;
use Resursbank\Ecom\Module\Payment\Enum\ActionType;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Ecom\Module\UserSettings\Repository as UserSettingsRepository;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Translator;
use Resursbank\Woocommerce\Util\WooCommerce;
use Throwable;
use WC_Order;

/**
 * Business logic relating to order management functionality.
 *
 * @phpcsSuppress SlevomatCodingStandard.Classes.ClassLength
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 * @SuppressWarnings(PHPMD.LongVariable)
 * @noinspection EfferentObjectCouplingInspection
 * @noinspection PhpClassHasTooManyDeclaredMembersInspection
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
        self::initRefund();
        self::initModify();

        // Status update transition handler for legacy (non-HPOS).
        add_action(
            'transition_post_status',
            'Resursbank\Woocommerce\Modules\OrderManagement\Filter\BeforeOrderStatusChange::exec',
            10,
            3
        );

        // Break status update if unavailable based on payment status (HPOS).
        add_action(
            'woocommerce_before_order_object_save',
            'Resursbank\Woocommerce\Modules\OrderManagement\Filter\BeforeOrderStatusChange::handlePostStatusTransitions',
            10,
            3
        );

        // Execute payment action AFTER the status has changed in WC.
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
     *
     * @throws ConfigException
     */
    public static function initModify(): void
    {
        if (!UserSettingsRepository::isEnabled(field: Field::MODIFY_ENABLED)) {
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
     *
     * @throws ConfigException
     */
    public static function initRefund(): void
    {
        if (!UserSettingsRepository::isEnabled(field: Field::REFUND_ENABLED)) {
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
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     * @noinspection PhpUnusedParameterInspection
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
     * Is the order rejected?
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
     * Resolve Resurs Bank payment from order.
     *
     * @param WC_Order $order
     * @return Payment|null
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
    public static function getPayment(WC_Order $order): ?Payment
    {
        // @todo There was previously a local caching layer used here to suppress unnecessary API calls. It didn't specify where this was relevant. Investigate if we need to put it back.
        return Repository::get(
            paymentId: Metadata::getPaymentId(order: $order)
        );
    }

    /**
     * Resolve WC_Order from ID if it was placed with Resurs Bank, else null.
     *
     * @param int $id
     * @return WC_Order|null
     * @throws ConfigException
     */
    public static function getOrder(int $id): ?WC_Order
    {
        try {
            if ($id === 0) {
                return null;
            }

            $orderObj = wc_get_order(the_order: $id);

            if (!is_object(value: $orderObj) || !$orderObj instanceof WC_Order) {
                throw new IllegalValueException(
                    message: 'Failed to obtain order data.'
                );
            }

            if (!Metadata::isValidResursPayment(order: $orderObj)) {
                return null;
            }

            return $orderObj;
        } catch (Throwable $error) {
            Config::getLogger()->error(message: $error);
            return null;
        }
    }

    /**
     * Log message to file on disk and message bag.
     *
     * @param string $message
     * @param Throwable $error
     * @param WC_Order|null $order
     */
    public static function logError(
        string $message,
        Throwable $error
    ): void {
        Log::error(error: $error);
        MessageBag::addError(message: $message);
    }

    /**
     * Log error from a Payment Action request (cancel, debit, credit, modify).
     * @throws Exception
     * @todo Think we can remove this, places where it's used should be moveable to Ecom, or just removable altogether.
     */
    public static function logActionError(
        ActionType $action,
        Throwable $error,
        string $reason = 'unknown reason'
    ): void {
        $actionString = str_replace(
            search: '_',
            replace: '-',
            subject: strtolower(string: $action->value)
        );

        self::logError(
            message: sprintf(
                Translator::translate(phraseId: "$actionString-action-failed"),
                strtolower(string: $reason)
            ),
            error: $error
        );
    }
}
