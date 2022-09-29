<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Cache;

use Redis as Server;
use Resursbank\Ecom\Exception\ValidationException;

use function is_string;

/**
 * Redis cache implementation.
 */
class Redis extends AbstractCache implements CacheInterface
{
    /**
     * @param string $host
     * @param int $port
     * @param string $password
     */
    public function __construct(
        private readonly string $host,
        private readonly int $port = 6379,
        private readonly string $password = ''
    ) {
    }

    /**
     * @return Server
     */
    private function connect(): Server
    {
        $result = new Server();
        $result->connect(host: $this->host, port: $this->port);

        if ($this->password !== '') {
            // NOTE: Naming parameter won't work because of method signature.
            $result->auth($this->password);
        }

        return $result;
    }

    /**
     * @inheritdoc
     * @throws ValidationException
     */
    public function read(string $key): ?string
    {
        $this->validateKey(key: $key);

        $result = $this->connect()->get(key: $key);

        return is_string(value: $result) ? $result : null;
    }

    /**
     * @inheritdoc
     * @throws ValidationException
     */
    public function write(string $key, string $data, int $ttl): void
    {
        $this->validateKey(key: $key);
        $this->connect()->setex(key: $key, expire: $ttl, value: $data);
    }

    /**
     * @inheritdoc
     * @throws ValidationException
     */
    public function clear(string $key): void
    {
        $this->validateKey(key: $key);

        // NOTE: Naming parameter won't work because of method signature.
        $this->connect()->del($key);
    }
}
