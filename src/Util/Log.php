<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Throwable;

/**
 * Business logic to log errors.
 */
class Log
{
    /**
     * Log Exception. In case there is a problem, display an error if possible.
     */
    public static function error(
        Throwable $error,
        string $msg = ''
    ): void {
        if ($msg !== '' && Admin::isAdmin()) {
            MessageBag::addError(msg: $msg);
        }

        try {
            Config::getLogger()->error(message: $error);
        } catch (ConfigException) {
            if (Admin::isAdmin()) {
                MessageBag::addError(
                    msg: 'Failed to log Exception. Did Config::setup execute?'
                );
            }
        }
    }
}
