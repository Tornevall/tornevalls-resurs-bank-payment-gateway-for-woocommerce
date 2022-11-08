<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\CacheException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Module\PaymentMethod\Repository;
use Resursbank\Ecom\Module\PaymentMethod\Widget\PaymentMethods as PaymentMethodsWidget;

/**
 * Payment methods section.
 *
 * @todo Translations should be moved to ECom. See WOO-802 & ECP-205.
 */
class PaymentMethods
{
    public const SECTION_ID = 'payment_methods';
    public const SECTION_TITLE = 'Payment Methods';

    /**
     * Returns settings provided by this section. These will be rendered by
     * WooCommerce to a form on the config page.
     *
     * @return array[]
     */
    public static function getSettings(): array
    {
        return [
            self::SECTION_ID => [
                'title' => self::SECTION_TITLE,
            ]
        ];
    }

    /**
     * Outputs a template string of a table with listed payment methods.
     *
     * @throws TranslationException
     * @throws ValidationException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws AuthException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws IllegalTypeException
     * @throws ReflectionException
     * @throws ApiException
     * @throws CacheException
     * @throws FilesystemException
     * @todo Exception handling. WOO-804.
     */
    public static function getOutput(string $storeId): string
    {
        return (new PaymentMethodsWidget(
            Repository::getPaymentMethods(storeId: $storeId)
        ))->content;
    }
}