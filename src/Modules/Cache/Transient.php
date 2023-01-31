<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Cache;

use JsonException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Cache\AbstractCache;
use Resursbank\Ecom\Lib\Cache\CacheInterface;

/**
 * ECom compliant implementation of Transient cache API.
 */
class Transient extends AbstractCache implements CacheInterface
{
    /**
     * @inheritdoc
     * @throws ValidationException|ConfigException
     */
    public function read(string $key): ?string
    {
        $this->validateKey(key: $key);

        $entry = $this->decodeEntry(
            data: (string) get_transient(transient: $key)
        );

         return (
            $entry !== null &&
            $this->validate(key: $key, entry: $entry)
        ) ? $entry->data : null;
    }

    /**
     * @inheritdoc
     * @throws ValidationException
     * @throws JsonException
     */
    public function write(string $key, string $data, int $ttl): void
    {
        $this->validateKey(key: $key);

        set_transient(
            transient: $key,
            value: $this->encodeEntry(data: $data, ttl: $ttl),
            expiration: $ttl
        );
    }

    /**
     * @inheritdoc
     * @throws ValidationException
     */
    public function clear(string $key): void
    {
        $this->validateKey(key: $key);

        delete_transient(transient: $key);
    }
}
