<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options;

use Resursbank\Woocommerce\Database\StringOption;

/**
 * Database interface for cache_dir in wp_options table.
 *
 * @todo Add value validation before appending value to database. Validate from Ecom. See WOO-800 and ECP-202.
 */
class LogDir extends StringOption
{
    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return self::NAME_PREFIX . 'log_dir';
    }

    /**
     * Defaults to attempting to use WooCommerce's log directory, if this can't be found we return ''.
     */
    public static function getDefault(): string
    {
        $uploadDirectory = wp_upload_dir(create_dir: false);

        if (
            is_array(value: $uploadDirectory) &&
            isset($uploadDirectory['basedir']) &&
            is_string(value: $uploadDirectory['basedir']) &&
            $uploadDirectory['basedir'] !== ''
        ) {
            return preg_replace(
                pattern: '/\/$/',
                replacement: '',
                subject: $uploadDirectory['basedir'] . '/wc-logs/'
            );
        }

        return '';
    }
}
