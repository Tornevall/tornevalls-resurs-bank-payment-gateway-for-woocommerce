<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\DataStorage\Mysql;

use InvalidArgumentException;

/**
 * Describes data required to access a MySQL database.
 */
class Config
{
    /**
     * @param string $username
     * @param string $password
     * @param string $database
     * @param string $host
     * @param int $port
     * @param string $engine
     * @param string $charset
     */
    public function __construct(
        public readonly string $username,
        public readonly string $password,
        public readonly string $database,
        public readonly string $host = 'localhost',
        public readonly int $port = 3306,
        public readonly string $engine = 'InnoDb',
        public readonly string $charset = 'utf8'
    ) {
        if ($this->username === '') {
            throw new InvalidArgumentException('Username cannot be empty.');
        }

        if ($this->password === '') {
            throw new InvalidArgumentException('Password cannot be empty.');
        }

        if ($this->database === '') {
            throw new InvalidArgumentException('Database cannot be empty.');
        }

        if ($this->host === '') {
            throw new InvalidArgumentException('Host cannot be empty.');
        }

        if ($this->port === 0) {
            throw new InvalidArgumentException('Port cannot be empty.');
        }

        if ($this->engine === '') {
            throw new InvalidArgumentException('Engine cannot be empty.');
        }

        if ($this->charset === '') {
            throw new InvalidArgumentException('Charset cannot be empty.');
        }
    }
}
