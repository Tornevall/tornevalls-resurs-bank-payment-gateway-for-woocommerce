<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options\Advanced;

use Resursbank\Woocommerce\Database\DataType\StringOption;
use Resursbank\Woocommerce\Database\OptionInterface;

use function is_array;
use function is_string;

/**
 * Implementation of resursbank_log_dir value in options table.
 */
class LogDir extends StringOption implements OptionInterface
{
    /**
     * @inheritdoc
     */
    public static function getName(): string
    {
        return self::NAME_PREFIX . 'log_dir';
    }

    /**
     * Defaults to wc-logs directory inside uploads directory.
     */
    public static function getDefault(): string
    {
        $result = parent::getDefault();
        $ulDir = self::getUploadDir();

        if ($ulDir === null) {
            return $result;
        }

        $dir = preg_replace(
            pattern: '/\/$/',
            replacement: '',
            subject: $ulDir . '/wc-logs/'
        );

        if (is_dir(filename: $dir) && is_writable(filename: $dir)) {
            $result = $dir;
        }

        return $result;
    }

    /**
     * Resolve path to WP upload directory.
     *
     * @todo This could perhaps be centralized in a Util class for Filesystem operations.
     */
    private static function getUploadDir(): ?string
    {
        $dir = wp_upload_dir(create_dir: false);

        return (
            is_array(value: $dir) &&
            isset($dir['basedir']) &&
            is_string(value: $dir['basedir']) &&
            $dir['basedir'] !== ''
        ) ? $dir['basedir'] : null;
    }
}
