<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model\PaymentMethod\ApplicationFormSpecResponse\ApplicationFormSpecElementResponse;

use Resursbank\Ecom\Lib\Model\Model;

/**
 * @SuppressWarnings(PHPMD.LongClassName)
 */
class ApplicationFormSpecWithDependencyRequiredIfValue extends Model
{
    /**
     * @param string|null $fieldName
     * @param string|null $pattern
     */
    public function __construct(
        public readonly ?string $fieldName = null,
        public readonly ?string $pattern = null
    ) {
    }
}
