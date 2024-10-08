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
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Module\PaymentMethod\Repository;
use Resursbank\Ecom\Module\PaymentMethod\Widget\PaymentMethods as PaymentMethodsWidget;
use Resursbank\Woocommerce\Util\Translator;

/**
 * Payment methods section.
 */
class PaymentMethods
{
    public const SECTION_ID = 'payment_methods';

    /**
     * Get translated title of tab.
     */
    public static function getTitle(): string
    {
        return Translator::translate(phraseId: 'payment-methods');
    }

    /**
     * Outputs a template string of a table with listed payment methods.
     *
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AuthException
     * @throws CacheException
     * @throws ConfigException
     * @throws CurlException
     * @throws FilesystemException
     * @throws TranslationException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public static function getOutput(string $storeId): string
    {
        // Hide the "Save changes" button since there are no fields here.
        $GLOBALS['hide_save_button'] = '1';

        if ($storeId !== '') {
            Repository::getCache()->clear();
        }

        return (new PaymentMethodsWidget(
            paymentMethods: Repository::getPaymentMethods()
        ))->content;
    }
}
