<?php

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Order\Filter;

use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Lib\Model\Payment\TaskStatusDetails;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Translator;
use Resursbank\Woocommerce\Util\WcSession;
use Resursbank\Woocommerce\Util\WordPress;
use Throwable;
use WC_Order;

// Prevent direct access.
if (!defined(constant_name: 'ABSPATH')) {
    exit;
}

/**
 * Event executed when failure page is reached.
 */
class Failure
{
    /** @noinspection PhpMissingClassConstantTypeInspection */
    private const SESSION_KEY_ERROR_MESSAGE = 'resursbank_order_error_msg';

    public static function init(): void
    {
        add_filter(
            'woocommerce_order_cancelled_notice',
            [self::class, 'captureAndRedirect'],
            10,
            1
        );

        add_filter(
            'the_content',
            [self::class, 'renderMessageOnCheckout'],
            10
        );
    }

    /**
     * Store failure reason in WC session and redirect to checkout.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function captureAndRedirect(string $message): string
    {
        try {
            $orderId = WordPress::getQueryParam('order_id');
            $orderId = $orderId !== '' ? (int)$orderId : 0;

            if ($orderId <= 0) {
                return $message;
            }

            $order = new WC_Order($orderId);

            if (!$order instanceof WC_Order) {
                return $message;
            }

            $paymentId = Metadata::getPaymentId(order: $order);

            if ($paymentId === '') {
                return $message;
            }

            WcSession::set(
                key: self::SESSION_KEY_ERROR_MESSAGE,
                value: self::getFailureReason(paymentId: $paymentId)
            );

            if (!headers_sent()) {
                wp_safe_redirect(wc_get_checkout_url());
                exit;
            }
        } catch (Throwable) {
            // Silent by design – never break checkout UX
        }

        return $message;
    }

    /**
     * Inject failure message on checkout page.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function renderMessageOnCheckout(string $content): string
    {
        if (!is_checkout()) {
            return $content;
        }

        $message = WcSession::get(key: self::SESSION_KEY_ERROR_MESSAGE);

        if ($message === '') {
            return $content;
        }

        WcSession::unset(key: self::SESSION_KEY_ERROR_MESSAGE);

        return
            '<div class="woocommerce-error" role="status">' .
            esc_html($message) .
            '</div>' .
            $content;
    }

    /**
     * Local replacement for Repository::getFailureReason().
     */
    private static function getFailureReason(string $paymentId): string
    {
        try {
            /** @var Payment $payment */
            $payment = Repository::get(paymentId: $paymentId);

            if ($payment->isRejectionReasonCreditDenied()) {
                return Translator::translate(
                    phraseId: 'credit-denied-try-again'
                );
            }

            /** @var TaskStatusDetails $task */
            $task = Repository::getTaskStatusDetails(paymentId: $paymentId);

            if (!$task->completed) {
                return Translator::translate(
                    phraseId: 'payment-cancelled-try-again'
                );
            }
        } catch (Throwable) {
            // Fall through to a generic message
        }

        return Translator::translate(phraseId: 'payment-failed-try-again');
    }
}
