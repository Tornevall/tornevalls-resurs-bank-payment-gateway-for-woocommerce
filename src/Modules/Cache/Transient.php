<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Cache;

use Resursbank\Ecom\Lib\Cache\AbstractCache;
use Resursbank\Ecom\Lib\Cache\CacheInterface;

use function is_string;

/**
 * ECom compliant implementation of Transient cache API.
 */
class Transient extends AbstractCache implements CacheInterface
{
    /**
     * @inheritDoc
     */
    public function read(string $key): ?string
    {
        $data = get_transient(transient: $key);

        return is_string(value: $data) && $data !== '' ? $data : null;
    }

    /**
     * @inheritDoc
     */
    public function write(string $key, string $data, int $ttl): void
    {
        set_transient(transient: $key, value: $data, expiration: $ttl);
    }

    /**
     * @inheritDoc
     */
    public function clear(string $key): void
    {
        delete_transient(transient: $key);
    }
}
