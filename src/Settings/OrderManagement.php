<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use Resursbank\Woocommerce\Database\Options\OrderManagement\EnableCancel;
use Resursbank\Woocommerce\Database\Options\OrderManagement\EnableCapture;
use Resursbank\Woocommerce\Database\Options\OrderManagement\EnableRefund;
use Resursbank\Woocommerce\Util\Translator;

/**
 * Order management settings section.
 */
class OrderManagement
{
    public const SECTION_ID = 'order-management';

    /**
     * Get translated title of API Settings tab on config page.
     */
    public static function getTitle(): string
    {
        return Translator::translate(phraseId: 'order-management');
    }

    /**
     * Returns settings provided by this section. These will be rendered by
     * WooCommerce to a form on the config page.
     */
    public static function getSettings(): array
    {
        return [
            self::SECTION_ID => [
                'enable_capture' => self::getEnableCapture(),
                'enable_cancel' => self::getEnableCancel(),
                'enable_refund' => self::getEnableRefund(),
            ],
        ];
    }

    /**
     * Return array for Enable Capture setting.
     */
    private static function getEnableCapture(): array
    {
        return [
            'id' => EnableCapture::getName(),
            'title' => Translator::translate(phraseId: 'enable-capture'),
            'desc' => self::getDesc(action: 'capture', status: 'completed'),
            'type' => 'checkbox',
            'default' => EnableCapture::getDefault(),
        ];
    }

    /**
     * Return array for Enable Cancel setting.
     */
    private static function getEnableCancel(): array
    {
        return [
            'id' => EnableCancel::getName(),
            'title' => Translator::translate(phraseId: 'enable-cancel'),
            'desc' => self::getDesc(action: 'cancel', status: 'cancelled'),
            'type' => 'checkbox',
            'default' => EnableCancel::getDefault(),
        ];
    }

    /**
     * Return array for Enable Refund setting.
     */
    private static function getEnableRefund(): array
    {
        return [
            'id' => EnableRefund::getName(),
            'title' => Translator::translate(phraseId: 'enable-refund'),
            'desc' => self::getDesc(action: 'refund', status: 'refunded'),
            'type' => 'checkbox',
            'default' => EnableRefund::getDefault(),
        ];
    }

    /**
     * Resolve setting description.
     */
    private static function getDesc(
        string $action,
        string $status
    ): string {
        $result = sprintf(
            Translator::translate(
                phraseId: 'automatic-order-management-on-status'
            ),
            Translator::translate(phraseId: $action),
            wc_get_order_status_name(status: $status)
        );

        if ($status === 'refunded') {
            $result .= ' ' . Translator::translate(
                phraseId: 'payment-must-be-captured-to-be-refunded'
            );
        }

        return $result;
    }
}
