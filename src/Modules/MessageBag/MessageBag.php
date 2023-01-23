<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\MessageBag;

use Resursbank\Woocommerce\Modules\MessageBag\Filter\AddNotices;

/**
 * Store admin notice messages to later be printed.
 */
class MessageBag
{
    /**
     * Array of errors messages.
     */
    private static array $errors = [];

    /**
     * Initialize this module.
     */
    public static function init(): void
    {
        AddNotices::register();
    }

    /**
     * Add notice message.
     */
    public static function addError(string $msg): void
    {
        self::$errors[] = $msg;
    }

    /**
     * Get all messages.
     */
    public static function getErrors(): array
    {
        return self::$errors;
    }
}
