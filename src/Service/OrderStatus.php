<?php

namespace ResursBank\Service;

use Exception;
use WC_Queue_Interface;

/**
 * Order status handler. Handles "complex" order statuses which is known to be affected by race conditions.
 * Class OrderStatus
 * @since 0.0.1.0
 */
class OrderStatus
{
    /**
     * HTTP Response Code for "digest problems".
     *
     * @var int
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status
     * @since 0.0.1.0
     */
    const HTTP_STATUS_DIGEST_IS_WRONG = 406;        // Matching with HTTP Status "Not acceptable".

    /**
     * HTTP Response Code for "order not found".
     * @var int
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status
     * @since 0.0.1.0
     */
    const HTTP_STATUS_ORDER_IS_GONE = 410;          // Marching with HTTP Status "Gone".

    /**
     * HTTP Response Code for "order not found".
     * @var int
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Status
     * @since 0.0.1.0
     */
    const HTTP_STATUS_ORDER_IS_NOT_OURS = 204;          // Marching with HTTP Status "No content".

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
     * @return WC_Queue_Interface
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function setOrderStatusWithNotice($order, $status, $notice)
    {
        self::getStaticQueue()->setOrderStatus(
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
     * @throws Exception
     * @since 0.0.1.0
     */
    public function setOrderStatus($order, $status, $notice)
    {
        WooCommerce::applyQueue(
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
    private static function getStaticQueue()
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
    public function getQueue()
    {
        return $this->queue;
    }
}
