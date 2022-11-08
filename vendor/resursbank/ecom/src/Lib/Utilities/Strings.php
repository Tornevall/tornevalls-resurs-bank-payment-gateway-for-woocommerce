<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Utilities;

use function strlen;

/**
 * Class for string manipulation.
 */
class Strings
{
    /**
     * Obfuscate strings between first and last character, just like RCO.
     *
     * @param string $string
     * @param int $startAt
     * @param int $endAt
     * @return string
     */
    public static function getObfuscatedString(string $string, int $startAt = 1, int $endAt = 1): string
    {
        $stringLength = strlen(string: $string);
        return $stringLength > $startAt - 1 ?
            substr(string: $string, offset: 0, length: $startAt) .
            str_repeat(string: '*', times: $stringLength - 2) .
            substr(
                string: $string,
                offset: $stringLength - $endAt,
                length: $stringLength - $endAt
            ) : $string;
    }
}
