<?php

namespace ResursBank\Model;

use stdClass;

class PaymentMethod
{
    /**
     * @var stdClass
     */
    private $raw;

    /**
     * @var string[]
     */
    private $properties = [
        'id',
        'description',
        'legalInfoLinks',
        'minLimit',
        'maxLimit',
        'type',
        'customerType',
        'specificType',
    ];

    /** @var string */
    private $id = '';

    /**
     * @var string
     */
    private $description = '';

    /**
     * @var array
     */
    private $legalInfoLinks = [];

    /**
     * @var int
     */
    private $minLimit = 0;

    /**
     * @var int
     */
    private $maxLimit = 0;

    /**
     * @var string
     */
    private $type = '';

    /**
     * @var array
     */
    private $customerType = [];

    /**
     * @var string
     */
    private $specificType = '';


    /**
     * @param $methodClass
     */
    public function __construct($methodClass)
    {
        $this->raw = $methodClass;
        $this->setPaymentMethodData();
    }

    /**
     * @return $this
     * @since 0.0.1.0
     */
    private function setPaymentMethodData()
    {
        foreach ($this->properties as $property) {
            $this->{$property} = $this->raw->{$property} ?? '';
        }

        // Fill in missing pieces.
        foreach ((array)$this->raw as $key => $value) {
            if (!in_array($key, $this->properties)) {
                $this->{$key} = $value;
            }
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getSpecificType(): string
    {
        return $this->specificType;
    }
}
