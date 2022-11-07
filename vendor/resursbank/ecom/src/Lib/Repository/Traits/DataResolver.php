<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

/** @noinspection PhpMultipleClassDeclarationsInspection */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Repository\Traits;

use Resursbank\Ecom\Exception\ApiException;
use stdClass;

use function is_array;
use function is_string;

/**
 * Resolve anonymous data from API response.
 */
trait DataResolver
{
    /**
     * Resolve data form API response.
     *
     * @param string|array|stdClass $data
     * @param string $extractProperty
     * @return stdClass|array|string
     * @throws ApiException
     */
    public function resolveResponseData(
        string|array|stdClass $data,
        string $extractProperty = ''
    ): stdClass|array|string {
        if (!$data instanceof stdClass) {
            throw new ApiException(
                message: 'Invalid response from API. Not an stdClass.',
                code: 500,
            );
        }

        if ($extractProperty !== '') {
            if (
                !property_exists(
                    object_or_class: $data,
                    property:  $extractProperty
                )
            ) {
                throw new ApiException(
                    message: 'Invalid response from API. Missing property ' .
                    $extractProperty,
                    code: 500,
                );
            }

            /** @psalm-suppress MixedAssignment */
            $data = $data->{$extractProperty};
        }

        if (
            !$data instanceof stdClass &&
            !is_string(value: $data) &&
            !is_array(value: $data)
        ) {
            throw new ApiException(
                message: 'Invalid response from API. Not an stdClass or array.',
                code: 500,
            );
        }

        return $data;
    }
}
