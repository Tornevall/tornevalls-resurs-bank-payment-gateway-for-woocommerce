<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Utilities;

use Resursbank\Ecom\Exception\SessionException;
use Resursbank\Ecom\Exception\SessionValueException;

use function is_string;

/**
 * Functionality to store and retrieve data from PHP session.
 *
 * @SuppressWarnings(PHPMD.Superglobals)
 */
class Session
{
    /**
     * Session key prefix.
     */
    public const PREFIX = 'resursbank_';

    /**
     * @param string $key
     * @param string $val
     * @return void
     * @throws SessionException
     */
    public function set(string $key, string $val): void
    {
        if (!$this->isAvailable()) {
            throw new SessionException(message: 'Session not available.');
        }

        $_SESSION[$this->getKey(key: $key)] = $val;
    }

    /**
     * @param string $key
     * @return string
     * @throws SessionException
     */
    public function get(string $key): string
    {
        $sessionKey = $this->getKey(key: $key);

        if (!$this->isAvailable()) {
            throw new SessionException(message: 'Session not available.');
        }

        if (!isset($_SESSION[$sessionKey])) {
            throw new SessionValueException(
                message: "$sessionKey not defined in session.",
                code: 404
            );
        }

        if (!is_string(value: $_SESSION[$sessionKey])) {
            throw new SessionValueException(
                message: "$sessionKey is not a string.",
                code: 415
            );
        }

        return $_SESSION[$sessionKey];
    }

    /**
     * @param string $key
     * @return string
     */
    public function getKey(string $key): string
    {
        return self::PREFIX . $key;
    }

    /**
     * @return bool
     */
    public function isAvailable(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }
}
