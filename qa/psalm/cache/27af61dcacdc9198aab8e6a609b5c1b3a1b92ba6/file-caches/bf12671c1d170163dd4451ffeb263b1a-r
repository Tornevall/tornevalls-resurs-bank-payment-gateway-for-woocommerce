<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Cache;

use Resursbank\Ecom\Exception\ValidationException;

/**
 * Disables caching.
 *
 * NOTE: The methods within this implementation will still validate the key for
 * consistency with other implementations, primarily to ensure we do not
 * introduce illegal keys during development with the cache disabled.
 */
class None extends AbstractCache implements CacheInterface
{
    /**
     * @inheritdoc
     * @throws ValidationException
     */
    public function read(string $key): ?string
    {
        // Validate key to keep consistency with other implementations.
        $this->validateKey(key: $key);

        return null;
    }

    /**
     * @inheritdoc
     * @throws ValidationException
     */
    public function write(string $key, string $data, int $ttl): void
    {
        // Validate key to keep consistency with other implementations.
        $this->validateKey(key: $key);
    }

    /**
     * @inheritdoc
     * @throws ValidationException
     */
    public function clear(string $key): void
    {
        // Validate key to keep consistency with other implementations.
        $this->validateKey(key: $key);
    }
}
