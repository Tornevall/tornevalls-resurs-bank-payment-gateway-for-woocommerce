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

        if (self::isWritableDir($dir)) {
            $result = $dir;
        }

        return $result;
    }

    /**
     * Resolve path to WP upload directory.
     */
    private static function getUploadDir(): ?string
    {
        $dir = wp_upload_dir(false);

        return (
            is_array(value: $dir) &&
            isset($dir['basedir']) &&
            is_string(value: $dir['basedir']) &&
            $dir['basedir'] !== ''
        ) ? $dir['basedir'] : null;
    }

    /**
     * Check if a directory is writable using WP_Filesystem.
     *
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     */
    private static function isWritableDir(string $dir): bool
    {
        global $wp_filesystem;

        if (!isset($wp_filesystem) || !is_object(value: $wp_filesystem)) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        if (!isset($wp_filesystem) || !is_object($wp_filesystem)) {
            return false;
        }

        return $wp_filesystem->is_dir($dir) && $wp_filesystem->is_writable(
            $dir
        );
    }
}
