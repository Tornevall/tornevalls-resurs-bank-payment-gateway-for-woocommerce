<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Network;

/**
 * Url helper.
 */
class Url
{
    /**
     * @param ApiType $apiType
     * @param bool $test
     * @param string $endpoint
     * @return string
     */
    public function getUrl(
        ApiType $apiType,
        bool $test,
        string $endpoint
    ): string {
        return $this->getBaseUrl(apiType: $apiType, test: $test) . $endpoint;
    }

    /**
     * @param ApiType $apiType
     * @param bool $test
     * @return string
     */
    public function getBaseUrl(ApiType $apiType, bool $test): string
    {
        return match ($apiType) {
            ApiType::MERCHANT => $test ?
                'https://apigw.integration.resurs.com/api/mock_merchant_api_service' :
                'https://apigw.resurs.com'
        };
    }
}
