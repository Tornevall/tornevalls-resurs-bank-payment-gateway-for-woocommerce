<?php

namespace ResursBank\Gateway;

/**
 * @since 0.0.1.0
 */
class ResursCheckout
{
    /**
     * Payment method id as seen by getPaymentMethods.
     * @var string
     * @since 0.0.1.0
     */
    public $id = 'RESURS_CHECKOUT';
    /**
     * Description as seen by getPaymentMethods.
     * @var string
     * @since 0.0.1.0
     */
    public $description = 'Resurs Checkout';
    /**
     * Info links like SEKKI, etc, as seen by getPaymentMethods.
     * @var array
     * @since 0.0.1.0
     */
    public $legalInfoLinks = [];
    /**
     * Minimum payment limit as seen by getPaymentMethods.
     * @var int
     * @since 0.0.1.0
     */
    public $minLimit;
    /**
     * Maximum payment limit  as seen by getPaymentMethods.
     * @var int
     * @since 0.0.1.0
     */
    public $maxLimit = PHP_INT_MAX;
    /**
     * Payment method type as seen by getPaymentMethods, but customized.
     * @var string
     * @since 0.0.1.0
     */
    public $type = 'iframe';
    /**
     * Customer type as seen by getPaymentMethods.
     * @var string|array
     * @since 0.0.1.0
     */
    public $customerType;
    /**
     * Specific payment method type as seen by getPaymentMethods.
     * @var string
     * @since 0.0.1.0
     */
    public $specificType;
}
