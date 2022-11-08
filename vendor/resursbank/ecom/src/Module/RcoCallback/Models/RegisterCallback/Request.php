<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\RcoCallback\Models\RegisterCallback;

use Resursbank\Ecom\Lib\Model\Model;

/**
 * Defines request to register callback.
 */
class Request extends Model
{
    /**
     * @param string $uriTemplate
     * @param string $basicAuthUserName
     * @param string $basicAuthPassword
     * @param DigestConfiguration $digestConfiguration
     */
    public function __construct(
        public string $uriTemplate,
        public string $basicAuthUserName,
        public string $basicAuthPassword,
        public DigestConfiguration $digestConfiguration
    ) {
    }
}
