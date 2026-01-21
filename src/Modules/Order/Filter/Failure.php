<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Order\Filter;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Lib\Log\Logger;
use Resursbank\Ecom\Module\Payment\Repository;
use Resursbank\Woocommerce\Modules\OrderManagement\OrderManagement;
use Resursbank\Woocommerce\Util\Metadata;
use Throwable;

/**
 * Redirects failed purchases to the checkout page with error message.
 */
class Failure
{
    /**
     * Session key containing error message. See init() method for info.
     */
    private const ERROR_MSG_SESSION_KEY = 'resursbank_order_error_msg';

    /**
     * Redirects failed purchases to the checkout page with error message.
     *
     * @return void
     */
    public static function init(): void
    {
        // Since we must redirect to the checkout, we cannot display the message
        // on the cancelled order page. We therefore store the message in
        // session to display it on the checkout page instead after redirect.
        add_filter(
            'woocommerce_order_cancelled_notice',
            function () {
                try {
                    // Ensure there is an order ID to work with, to avoid
                    // unnecessary log entries.
                    $orderId = $_GET['order_id'] ?? 0;

                    if (!$orderId) {
                        return;
                    }

                    // Only execute for Resurs Bank orders.
                    $order = OrderManagement::getOrder(id: $orderId);

                    if (!$order || !Metadata::isValidResursPayment(order: $order)) {
                        return;
                    }

                    // Resolve failure reason and store in session.
                    Config::getSessionHandler()->set(
                        key: self::ERROR_MSG_SESSION_KEY,
                        val: Repository::getFailureReason(
                            paymentId: Metadata::getPaymentId(order: $order)
                        )
                    );

                    // Redirect to the checkout page.
                    wp_redirect(location: wc_get_checkout_url());
                    exit;
                } catch (Throwable $error) {
                    Logger::error(message: $error);
                }
            },
            10,
            1
        );

        // Display message on checkout page via the_content.
        add_filter(
            'the_content',
            function ($content) {
                if (!is_checkout()) {
                    return $content;
                }

                try {
                    $sessionHandler = Config::getSessionHandler();
                    $message = $sessionHandler->get(key: self::ERROR_MSG_SESSION_KEY);

                    if ($message) {
                        // Prepend the error message to the content.
                        $content = '<div class="woocommerce-error"><p>' .
                            esc_html(text: $message) .
                            '</p></div>' . $content;

                        // Clear the message from session, ensuring it won't
                        // be displayed again.
                        $sessionHandler->delete(key: self::ERROR_MSG_SESSION_KEY);
                    }
                } catch (Throwable $error) {
                    Logger::error(message: $error);
                }

                return $content;
            },
            10
        );
    }
}
