<?php

namespace ResursBank\Module;

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
use Resursbank\Ecom\Module\Payment\Enum\Status as PaymentStatus;
use Resursbank\Ecom\Module\Payment\Repository as PaymentRepository;
use WC_Order;

/**
 * Order status handler. Centralization for received callbacks and user interactions via thankyou..
 */
class OrderStatus
{
    /**
     * @param WC_Order $order WooCommerce order.
     * @param string $paymentId Resurs payment id (uuid based).
     * @return void
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
     * @noinspection PhpUnhandledExceptionInspection
     */
    public static function setWcOrderStatus(WC_Order $order, string $paymentId): void
    {
        // Try-catch should not be placed here, as any exceptions will be caught at the json-response level.
        $resursPayment = PaymentRepository::get(paymentId: $paymentId);

        // Silently handle statuses.
        if (!$order->has_status(status: ['on-hold', 'processing', 'completed', 'cancelled'])) {
            match ($resursPayment->status) {
                PaymentStatus::ACCEPTED => $order->payment_complete(),
                PaymentStatus::REJECTED => $order->update_status(
                    new_status: 'failed',
                    note: 'Payment rejected by Resurs.'
                ),
                default => $order->update_status(
                    new_status: 'on-hold',
                    note: 'Payment is waiting for more information from Resurs.'
                ),
            };
        }
    }
}
