<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Cache;

use Resursbank\Ecom\Exception\ValidationException;

/**
 * Basic methods utilised by all cache implementations.
 */
abstract class AbstractCache
{
    /**
     * Cache prefix, ensures our cache keys are unique.
     */
    public const CACHE_KEY_PREFIX = 'resursbank-ecom-';

    /**
     * To ensure our keys will function regardless of cache implementation we
     * limit what characters may be utilised as part of the key. The key cannot
     * be empty.
     *
     * @param string $key
     * @return void
     * @throws ValidationException
     */
    public function validateKey(string $key): void
    {
        if ($key === '') {
            throw new ValidationException(
                message: 'Cache key cannot be empty.'
            );
        }

        if (preg_match(pattern: '/[^a-zA-Z\d\-_]/', subject: $key)) {
            throw new ValidationException(
                message: 'Cache key contains illegal characters.'
            );
        }

        if (!str_starts_with(haystack: $key, needle: self::CACHE_KEY_PREFIX)) {
            throw new ValidationException(
                message: 'Cache key must be prefixed with ' .
                    self::CACHE_KEY_PREFIX
            );
        }
    }

    /**
     * Get prefixed cache key.
     *
     * @param string $key
     * @return string
     */
    public static function getKey(string $key): string
    {
        return self::CACHE_KEY_PREFIX . $key;
    }
}
