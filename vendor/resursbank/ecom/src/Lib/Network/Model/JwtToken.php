<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Network\Model;

/**
 * Describes JWT token.
 */
class JwtToken
{
    /**
     * @param string $accessToken
     * @param string $tokenType
     * @param int $validUntil
     */
    public function __construct(
        public readonly string $accessToken,
        public readonly string $tokenType,
        public readonly int $validUntil,
    ) {
    }
}
