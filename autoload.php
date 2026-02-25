<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

/**
 * Automatically require PHP classes from WooCommerce module and Ecom library.
 */
class ResursBankEcomAutoloader
{
    public static function exec(string $class): void
    {
        $map = [
            'Resursbank\\Ecom' => 'vendor/ecom/src',
            'Resursbank\\Woocommerce' => 'src'
        ];

        foreach ($map as $namespace => $dir) {
            if (!str_starts_with(haystack: $class, needle: $namespace)) {
                continue;
            }

            $relative = str_replace(
                search: '\\',
                replace: '/',
                subject: substr(
                    string: $class,
                    offset: strlen($namespace)
                )
            ) . '.php';

            $path = __DIR__ . '/' . $dir . $relative;

            if (!is_file($path)) {
                continue;
            }

            require $path;
        }
    }
}

// Register autoloader.
spl_autoload_register('ResursBankEcomAutoloader::exec');
