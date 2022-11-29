<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model\PaymentMethod;

use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Lib\Model\PaymentMethod\ApplicationFormSpecResponse\ApplicationFormSpecElementResponse;
use Resursbank\Ecom\Lib\Model\PaymentMethod\ApplicationFormSpecResponse\ApplicationFormSpecElementResponse\Type;
use Resursbank\Ecom\Lib\Model\PaymentMethod\ApplicationFormSpecResponse\ApplicationFormSpecElementResponseCollection;

use function array_filter;
use function in_array;

/**
 * Response object for application data specification calls
 */
class ApplicationFormSpecResponse extends Model
{
    /**
     * @param ApplicationFormSpecElementResponseCollection|null $elements
     */
    public function __construct(
        public readonly ?ApplicationFormSpecElementResponseCollection $elements = null
    ) {
    }

    /**
     * Check if response contains a field with the specified name
     *
     * @param string $fieldName
     * @return bool
     */
    public function hasField(string $fieldName): bool
    {
        if (!isset($this->elements)) {
            return false;
        }

        /** @var ApplicationFormSpecElementResponse $element */
        foreach ($this->elements as $element) {
            if ($element->fieldName === $fieldName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return a collection with only
     * @throws IllegalTypeException
     */
    public function getFieldsByType(Type $type): ApplicationFormSpecElementResponseCollection
    {
        if (!isset($this->elements)) {
            return new ApplicationFormSpecElementResponseCollection(data: []);
        }

        $fields = array_filter(
            array: $this->elements->toArray(),
            callback: static function (ApplicationFormSpecElementResponse $element) use ($type) {
                return $element->type === $type;
            }
        );

        return new ApplicationFormSpecElementResponseCollection(data: $fields);
    }

    /**
     * Filters out specified fields from field collection
     *
     * @param string $property
     * @param array $fields
     * @return self
     * @throws IllegalTypeException
     */
    public function filter(string $property, array $fields): self
    {
        if (!isset($this->elements)) {
            return $this; // No point in filtering if we don't have a collection
        }

        $filtered = array_filter(
            array: $this->elements->toArray(),
            callback: static function (ApplicationFormSpecElementResponse $element) use ($fields, $property) {
                return !in_array(
                    needle: $element->{$property},
                    haystack: $fields,
                    strict: true
                );
            }
        );

        return new self(elements: new ApplicationFormSpecElementResponseCollection(data: $filtered));
    }
}
