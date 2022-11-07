<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model\PaymentMethod\ApplicationFormSpecResponse;

use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Lib\Model\PaymentMethod\ApplicationFormSpecResponse\ApplicationFormSpecElementResponse\ApplicationFormSpecElementOptionResponseCollection; //phpcs:ignore
use Resursbank\Ecom\Lib\Model\PaymentMethod\ApplicationFormSpecResponse\ApplicationFormSpecElementResponse\ApplicationFormSpecWithDependencyRequiredIfValue; //phpcs:ignore
use Resursbank\Ecom\Lib\Model\PaymentMethod\ApplicationFormSpecResponse\ApplicationFormSpecElementResponse\Type;
use Resursbank\Ecom\Lib\Validation\StringValidation;

/**
 * Individual application data specification response item (represents a single input field or label)
 *
 * @SuppressWarnings(PHPMD.LongVariable)
 */
class ApplicationFormSpecElementResponse extends Model
{
    /**
     * @param Type $type
     * @param string $label
     * @param string|null $fieldName
     * @param string|null $description
     * @param bool|null $required
     * @param string|null $pattern
     * @param string|null $patternValidationErrorMessage
     * @param string|null $defaultValue
     * @param ApplicationFormSpecElementOptionResponseCollection|null $options
     * @param int|null $maxLength
     * @param int|null $min
     * @param int|null $max
     * @param ApplicationFormSpecWithDependencyRequiredIfValue|null $requiredIfValue
     * @param StringValidation $stringValidation
     * @throws EmptyValueException
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        public readonly Type $type,
        public readonly string $label,
        public readonly ?string $fieldName = null,
        public readonly ?string $description = null,
        public readonly ?bool $required = null,
        public readonly ?string $pattern = null,
        public readonly ?string $patternValidationErrorMessage = null,
        public readonly ?string $defaultValue = null,
        public readonly ?ApplicationFormSpecElementOptionResponseCollection $options = null,
        public readonly ?int $maxLength = null,
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        public readonly ?ApplicationFormSpecWithDependencyRequiredIfValue $requiredIfValue = null,
        private readonly StringValidation $stringValidation = new StringValidation()
    ) {
        $this->validateLabel();
    }

    /**
     * @return void
     * @throws EmptyValueException
     */
    private function validateLabel(): void
    {
        $this->stringValidation->notEmpty(value: $this->label);
    }
}
