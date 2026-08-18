<?php

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Order\Filter;

use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Lib\Model\Payment\TaskStatusDetails;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Util\HtmlSanitizer;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Translator;
use Resursbank\Woocommerce\Util\WcSession;
use Resursbank\Woocommerce\Util\WordPress;
use Throwable;
use WC_Order;

// Prevent direct access.
if (!defined('ABSPATH')) {
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
        // Intercept cancel order requests BEFORE WooCommerce processes them (WC uses priority 20)
        // This prevents order cancellation when a payment has been superseded
        add_action(
            'wp_loaded',
            [self::class, 'interceptCancelOrder'],
            10
        );

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
     * Intercept cancel order requests before WooCommerce processes them.
     *
     * This runs at wp_loaded priority 10, before WooCommerce's cancel_order
     * handler at priority 20. If the cancel token doesn't match, we redirect
     * away immediately, preventing WooCommerce from cancelling the order.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.ExitExpression)
     */
    public static function interceptCancelOrder(): void
    {
        // Check if this is a cancel order request (same conditions as WooCommerce)
        if (
            !isset($_GET['cancel_order']) ||
            !isset($_GET['order']) ||
            !isset($_GET['order_id'])
        ) {
            return;
        }

        try {
            $orderId = absint($_GET['order_id']);

            if ($orderId <= 0) {
                return;
            }

            $order = wc_get_order($orderId);

            if (!$order instanceof WC_Order) {
                return;
            }

            // Check if this is a Resurs Bank order by looking for our cancel token
            $orderToken = Metadata::getOrderMeta(
                order: $order,
                key: Metadata::KEY_CANCEL_TOKEN
            );

            if ($orderToken === '') {
                // No token on order - not a Resurs payment or legacy order, let WC handle it
                return;
            }

            // Get token from URL
            $urlToken = isset($_GET['rb_cancel_token'])
                ? sanitize_text_field(wp_unslash($_GET['rb_cancel_token']))
                : '';

            // If tokens don't match, the payment was superseded - redirect silently
            if ($urlToken !== $orderToken) {
                wp_safe_redirect(wc_get_checkout_url());
                exit;
            }
        } catch (Throwable) {
            // On any error, let WooCommerce handle it normally
        }
    }

    /**
     * Store failure reason in WC session and redirect to checkout.
     *
     * Checks the cancel token from URL against the one stored on the order.
     * If they don't match, it means a new payment was created (the old one was
     * cancelled in process_payment) and this failure redirect should be ignored.
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

            // Check if the cancel token in the URL matches the one on the order.
            // If they don't match, the payment was superseded by a new one and
            // we should redirect silently without cancelling the order.
            $urlToken = WordPress::getQueryParam('rb_cancel_token');
            $orderToken = Metadata::getOrderMeta(
                order: $order,
                key: Metadata::KEY_CANCEL_TOKEN
            );

            if ($urlToken !== '' && $orderToken !== '' && $urlToken !== $orderToken) {
                // Token mismatch - a new payment was created, ignore this failure
                if (!headers_sent()) {
                    wp_safe_redirect(wc_get_checkout_url());
                    die;
                }
                return $message;
            }

            // Use getOrderMeta directly because getPaymentId() throws when empty
            $paymentId = Metadata::getOrderMeta(
                order: $order,
                key: Metadata::KEY_PAYMENT_ID
            );

            // If no payment ID attached, the payment was detached intentionally
            // (cancelled from process_payment to allow a new checkout attempt).
            // Don't show an error or cancel the order - just redirect to checkout silently.
            if ($paymentId === '') {
                if (!headers_sent()) {
                    wp_safe_redirect(wc_get_checkout_url());
                    die;
                }
                return $message;
            }

            WcSession::set(
                key: self::SESSION_KEY_ERROR_MESSAGE,
                value: self::getFailureReason(paymentId: $paymentId)
            );

            if (!headers_sent()) {
                wp_safe_redirect(wc_get_checkout_url());
                die;
            }
        } catch (Throwable) {
            // Silent by design – never break checkout UX
        }

        return $message;
    }

    /**
     * Inject failure message on checkout page.
     *
     * This is a the_content filter callback, so the return value must be escaped.
     * We apply wp_kses separately on the error message and content to preserve
     * their respective HTML structures, then combine them in the error div.
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

        // Apply wp_kses separately on message and content with their respective allowlists
        // then combine in the error div for return
        return '<div class="woocommerce-error" role="status">' .
            wp_kses(
                $message,
                HtmlSanitizer::getErrorMessageAllowlist()
            ) .
            '</div>' .
            wp_kses_post($content);
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
                return esc_html(
                    Translator::translate(
                        phraseId: 'credit-denied-try-again'
                    )
                );
            }

            /** @var TaskStatusDetails $task */
            $task = Repository::getTaskStatusDetails(paymentId: $paymentId);

            if (!$task->completed) {
                return esc_html(Translator::translate(
                    phraseId: 'payment-cancelled-try-again'
                ));
            }
        } catch (Throwable) {
            // Fall through to a generic message
        }

        return esc_html(
            Translator::translate(phraseId: 'payment-failed-try-again')
        );
    }
}
