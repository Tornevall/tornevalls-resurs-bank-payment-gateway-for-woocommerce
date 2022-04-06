<?php

namespace ResursBank\Service;

use Exception;
use WC_Queue_Interface;

/**
 * Order status handler. Handles "complex" order statuses which is known to be affected by race conditions.
 * @since 0.0.1.0
 */
class OrderStatus
{
    /**
     * HTTP Response Code for "digest problems".
     * Matching with HTTP Status "Not acceptable".
     * @var int
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status
     * @since 0.0.1.0
     */
    const HTTP_RESPONSE_DIGEST_IS_WRONG = 406;

    /**
     * HTTP Response Code for "order not found".
     * Matching with HTTP Status "Gone".
     * @var int
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status
     * @since 0.0.1.0
     */
    const HTTP_RESPONSE_GONE_NOT_OURS = 410;

    /**
     * HTTP Response Code for "order not found".
     * Callback treated when order was not found but accepted (option accept_rejected_callbacks enabled).
     * Matching with HTTP Status "Accepted".
     * @var int
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status
     * @since 0.0.1.0
     */
    const HTTP_RESPONSE_NOT_OURS_BUT_ACCEPTED = 202;

    /**
     * HTTP Response Code for a successfully handled callback.
     * Callback treated fully OK.
     * Matching with HTTP Status "No content" (default for success).
     * @var int
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status
     * @since 0.0.1.0
     */
    const HTTP_RESPONSE_OK = 204;

    /**
     * HTTP Response Code for test callbacks. 209 is said to not be used by anyone for the moment.
     * @var int
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status
     * @since 0.0.1.0
     */
    const HTTP_RESPONSE_TEST_OK = 204;

    /**
     * What we handle through static calls.
     * @var OrderStatus
     * @since 0.0.1.0
     */
    private static $staticQueue;

    /**
     * What we handle the normal way.
     * @var WC_Queue_Interface
     * @since 0.0.1.0
     */
    private $queue;

    /**
     * Initialize WC()->queue.
     * @since 0.0.1.0
     */
    public function __construct()
    {
        $this->queue = WC()->queue();
    }

    /**
     * Order status helper.
     *
     * @param $order
     * @param $status
     * @param $notice
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function setOrderStatusWithNotice($order, $status, $notice): string
    {
        return (self::getStaticQueue())->setOrderStatus(
            $order,
            $status,
            $notice
        );
    }

    /**
     * This is where we push the order statuses into a queue. Can be called directly if not using the static method.
     *
     * @param $order
     * @param $status
     * @param $notice
     * @return string
     * @throws Exception
     * @since 0.0.1.0
     */
    public function setOrderStatus($order, $status, $notice): string
    {
        return WooCommerce::applyQueue(
            'updateOrderStatusByQueue',
            [
                WooCommerce::getProperOrder($order, 'id'),
                $status,
                $notice,
            ]
        );
    }

    /**
     * Initialize internal handler.
     * @return OrderStatus
     * @since 0.0.1.0
     */
    private static function getStaticQueue(): OrderStatus
    {
        if (empty(self::$staticQueue)) {
            self::$staticQueue = new OrderStatus();
        }

        return self::$staticQueue;
    }

    /**
     * Prepare for WC_Queue.
     * @return WC_Queue_Interface
     * @since 0.0.1.0
     */
    public function getQueue(): WC_Queue_Interface
    {
        return $this->queue;
    }
}
