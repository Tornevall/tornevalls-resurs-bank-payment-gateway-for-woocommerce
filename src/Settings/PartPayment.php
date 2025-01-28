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
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Validation\StringValidation;
use Resursbank\Ecom\Module\PaymentMethod\Repository;
use Resursbank\Ecom\Module\Store\Repository as StoreRepository;
use Resursbank\Woocommerce\Database\Options\Advanced\StoreId;
use Resursbank\Woocommerce\Database\Options\PartPayment\Enabled;
use Resursbank\Woocommerce\Database\Options\PartPayment\Limit;
use Resursbank\Woocommerce\Database\Options\PartPayment\PaymentMethod;
use Resursbank\Woocommerce\Database\Options\PartPayment\PaymentMethod as PaymentMethodOption;
use Resursbank\Woocommerce\Database\Options\PartPayment\Period;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Util\Translator;
use Resursbank\Woocommerce\Util\WooCommerce;
use Throwable;

/**
 * Generates the settings form for the Part payment module
 */
class PartPayment
{
    public const SECTION_ID = 'partpayment';

    /**
     * Default minimum limit for part payment threshold.
     */
    public const MINIMUM_THRESHOLD_LIMIT_DEFAULT = 150;

    /**
     * Minimum limit for part payment threshold in Finland.
     */
    public const MINIMUM_THRESHOLD_LIMIT_FI = 15;

    /**
     * Get translated title of tab.
     */
    public static function getTitle(): string
    {
        return Translator::translate(phraseId: 'part-payment');
    }

    /**
     * Register event handlers.
     *
     * @SuppressWarnings(PHPMD.EmptyCatchBlock)
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function init(): void
    {
        add_action(
            'updated_option',
            'Resursbank\Woocommerce\Settings\PartPayment::validateLimit',
            10,
            3
        );

        add_filter(
            'woocommerce_admin_settings_sanitize_option',
            [self::class, 'sanitizeResursPartPaymentValues'],
            10,
            3
        );
    }

    /**
     * Sanitize values relating to part payment widget values.
     *
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     * @SuppressWarnings(PHPMD.CamelCaseParameterName)
     */
    public static function sanitizeResursPartPaymentValues(mixed $value, mixed $option, mixed $raw_value): mixed
    {
        if (self::isValidPeriodOption(option: $option, raw_value: $raw_value)) {
            return $raw_value;
        }

        if (
            self::isValidPaymentOrStoreIdOption(
                option: $option,
                raw_value: $raw_value
            )
        ) {
            return $raw_value;
        }

        return $value;
    }

    /**
     * Get settings.
     */
    public static function getSettings(): array
    {
        return [
            self::SECTION_ID => [
                'enabled' => self::getEnabledSetting(),
                'payment_method' => self::getPaymentMethodSetting(),
                'period' => self::getPeriodSetting(),
                'limit' => self::getLimitSetting(),
            ],
        ];
    }

    /**
     * Validate Limit setting and show error messages if the user hasn't configured the widget correctly
     *
     * @throws ApiException
     * @throws AuthException
     * @throws CacheException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws Throwable
     * @throws ValidationException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    // phpcs:ignore
    public static function validateLimit(mixed $option, mixed $old, mixed $new): void
    {
        global $overrideSavedCountryCode;

        if ($option !== Limit::getName() && $option !== StoreId::getName()) {
            return;
        }

        $paymentMethodId = PaymentMethodOption::getData();
        $storeId = StoreId::getData();
        $period = Period::getData();

        if (empty($storeId)) {
            MessageBag::addError(message: Translator::translate(
                phraseId: 'limit-missing-store-id'
            ));
            return;
        }

        if (empty($paymentMethodId)) {
            MessageBag::addError(message: Translator::translate(
                phraseId: 'limit-missing-payment-method'
            ));
            return;
        }

        if (empty($period)) {
            MessageBag::addError(message: Translator::translate(
                phraseId: 'limit-missing-period'
            ));
            return;
        }

        if ($option === StoreId::getName()) {
            try {
                $newStore = StoreRepository::getStores()->filterById(id: $new);
                $countryCode = $newStore->countryCode->value;
                $currentStoreCountry = WooCommerce::getStoreCountry();
                $countryGroup1 = ['SE', 'NO', 'DK'];
                $countryGroup2 = ['FI'];

                if (
                    (
                        in_array($currentStoreCountry, $countryGroup1) &&
                        in_array($countryCode, $countryGroup2)
                    ) ||
                    (
                        in_array($currentStoreCountry, $countryGroup2) &&
                        in_array($countryCode, $countryGroup1)
                    )
                ) {
                    $overrideSavedCountryCode = $countryCode;

                    if ($countryCode === 'FI') {
                        update_option(
                            'resursbank_part_payment_limit',
                            self::MINIMUM_THRESHOLD_LIMIT_FI
                        );
                    } else {
                        update_option(
                            'resursbank_part_payment_limit',
                            self::MINIMUM_THRESHOLD_LIMIT_DEFAULT
                        );
                    }
                }
            } catch (Throwable) {
                // Ignore the exception.
            }

            return;
        }

        $customerCountry = get_option('woocommerce_default_country');

        try {
            $storeCountry = WooCommerce::getStoreCountry() ?? $customerCountry;

            // Do not touch anything if overrider is active.
            if (isset($overrideSavedCountryCode)) {
                return;
            }
        } catch (Throwable) {
            $storeCountry = $customerCountry;
        }

        $paymentMethod = Repository::getById(paymentMethodId: $paymentMethodId);

        if ($paymentMethod === null) {
            MessageBag::addError(message: Translator::translate(
                phraseId: 'limit-failed-to-load-payment-method'
            ));
            return;
        }

        $maxLimit = $paymentMethod->maxPurchaseLimit;
        $minLimit = ($storeCountry === 'FI' ? self::MINIMUM_THRESHOLD_LIMIT_FI : self::MINIMUM_THRESHOLD_LIMIT_DEFAULT);

        if ($new < 0) {
            MessageBag::addError(message: Translator::translate(
                phraseId: 'limit-new-value-not-positive'
            ));
        } elseif ($new > $maxLimit) {
            MessageBag::addError(message: str_replace(
                search: '%1',
                replace: (string)$maxLimit,
                subject: Translator::translate(
                    phraseId: 'limit-new-value-above-max'
                )
            ));
        } elseif ($new < $minLimit) {
            update_option('resursbank_part_payment_limit', $minLimit);
            MessageBag::addError(message: str_replace(
                search: '%1',
                replace: (string)$minLimit,
                subject: Translator::translate(
                    phraseId: 'limit-new-value-below-min'
                )
            ));
        }
    }

    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     * @SuppressWarnings(PHPMD.CamelCaseParameterName)
     */
    private static function isValidPeriodOption(mixed $option, mixed $raw_value): bool
    {
        return $option['id'] === Period::getName() && (int)$raw_value > 0;
    }

    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     * @SuppressWarnings(PHPMD.CamelCaseParameterName)
     */
    private static function isValidPaymentOrStoreIdOption(mixed $option, mixed $raw_value): bool
    {
        if (
            $raw_value !== '' &&
            (
                $option['id'] === PaymentMethod::getName() ||
                $option['id'] === StoreId::getName()
            )
        ) {
            return self::isValidUuid(raw_value: $raw_value);
        }

        return false;
    }

    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     * @SuppressWarnings(PHPMD.CamelCaseParameterName)
     */
    private static function isValidUuid(mixed $raw_value): bool
    {
        try {
            $stringValidation = new StringValidation();
            return $stringValidation->isUuid(value: $raw_value);
        } catch (Throwable) {
            // Log error or handle exception if needed
            return false;
        }
    }

    /**
     * Fetches the enabled setting.
     */
    private static function getEnabledSetting(): array
    {
        return [
            'id' => Enabled::getName(),
            'title' => Translator::translate(
                phraseId: 'part-payment-widget-enabled'
            ),
            'type' => 'checkbox',
            'default' => Enabled::getDefault(),
        ];
    }

    /**
     * Fetches the payment_method setting.
     */
    private static function getPaymentMethodSetting(): array
    {
        return [
            'id' => PaymentMethodOption::getName(),
            'title' => Translator::translate(phraseId: 'payment-method'),
            'type' => 'select',
            'default' => PaymentMethodOption::getDefault(),
            'options' => [],
            'desc' => Translator::translate(
                phraseId: 'part-payment-payment-method'
            ),
        ];
    }

    /**
     * Fetches the period setting.
     */
    private static function getPeriodSetting(): array
    {
        return [
            'id' => Period::getName(),
            'title' => Translator::translate(phraseId: 'annuity-period'),
            'type' => 'select',
            'default' => Period::getDefault(),
            'options' => [],
            'desc' => Translator::translate(
                phraseId: 'part-payment-annuity-period'
            ),
        ];
    }

    /**
     * Fetches the limit setting.
     */
    private static function getLimitSetting(): array
    {
        return [
            'id' => Limit::getName(),
            'title' => Translator::translate(phraseId: 'limit'),
            'type' => 'text',
            'default' => Limit::getDefault(),
            'desc' => Translator::translate(phraseId: 'part-payment-limit'),
        ];
    }
}
