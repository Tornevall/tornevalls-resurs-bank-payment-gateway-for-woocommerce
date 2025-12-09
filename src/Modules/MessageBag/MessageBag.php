<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\MessageBag;

use Resursbank\Woocommerce\Util\Admin;
use Resursbank\Woocommerce\Util\Log;
use Throwable;

use function defined;
use function function_exists;

/**
 * Simplified message bag using native WordPress/WooCommerce functions.
 */
class MessageBag
{
    private const SETTINGS_GROUP = 'resursbank_messages';

    /**
     * Static array to store messages during AJAX requests.
     */
    private static array $ajaxMessages = [];

    /**
     * Initialize this module.
     */
    public static function init(): void
    {
        add_action(
            'admin_notices',
            'Resursbank\Woocommerce\Modules\MessageBag\MessageBag::printMessages'
        );
    }

    /**
     * Add message to bag.
     */
    public static function add(string $message, Type $type): void
    {
        try {
            if ($message === '') {
                return;
            }

            if (Admin::isAdmin()) {
                if (defined(constant_name: 'DOING_AJAX') && DOING_AJAX) {
                    // Store in memory for AJAX requests to return in response
                    self::$ajaxMessages[] = [
                        'message' => $message,
                        'type' => $type->value,
                    ];
                    return;
                }

            } elseif (function_exists(function: 'wc_add_notice')) {
                // Use WooCommerce native notice system for frontend
                wc_add_notice(message: $message, notice_type: $type->toWooCommerceType());
            }
        } catch (Throwable $e) {
            Log::error(error: $e);
        }
    }

    /**
     * Add error message.
     */
    public static function addError(string $message): void
    {
       // self::add(message: $message, type: Type::ERROR);
    }

    /**
     * Add success message.
     */
    public static function addSuccess(string $message): void
    {
        self::add(message: $message, type: Type::SUCCESS);
    }

    /**
     * Get messages for AJAX responses.
     *
     * @return array Array of messages with 'message' and 'type' keys
     */
    public static function getBag(): array
    {
        return self::$ajaxMessages;
    }

    /**
     * Clear messages (for AJAX requests).
     */
    public static function clear(): void
    {
        self::$ajaxMessages = [];
    }

    /**
     * Print message bag (uses WordPress native settings_errors).
     */
    public static function printMessages(): void
    {
        try {
            settings_errors(setting: self::SETTINGS_GROUP);
        } catch (Throwable $e) {
            Log::error(error: $e);
        }
    }
}
