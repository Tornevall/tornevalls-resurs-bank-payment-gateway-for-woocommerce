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
use Resursbank\Ecom\Module\AnnuityFactor\Repository as AnnuityRepository;
use Resursbank\Ecom\Module\PaymentMethod\Repository;
use Resursbank\Woocommerce\Database\Options\Advanced\StoreId;
use Resursbank\Woocommerce\Database\Options\PartPayment\Enabled;
use Resursbank\Woocommerce\Database\Options\PartPayment\Limit;
use Resursbank\Woocommerce\Database\Options\PartPayment\PaymentMethod;
use Resursbank\Woocommerce\Database\Options\PartPayment\PaymentMethod as PaymentMethodOption;
use Resursbank\Woocommerce\Database\Options\PartPayment\Period;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;

/**
 * Generates the settings form for the Part payment module
 */
class PartPayment
{
    public const SECTION_ID = 'partpayment';

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
            static function ($value, $option, $raw_value) {
                $allowedRawOptionIds = [Period::getName(), PaymentMethod::getName()];

                // Use raw values for some options, making sure data are saved even if values are not allowed.
                // As the id is bypassed from woocommerce, we should avoid strict checking.
                if (
                    in_array(
                        $option['id'],
                        $allowedRawOptionIds,
                        strict: false
                    ) &&
                    self::isValidRawValue(rawValue: $raw_value)
                ) {
                    return $raw_value;
                }

                return $value;
            },
            10,
            3
        );
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
     * @throws ValidationException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @noinspection PhpUnusedParameterInspection
     */
    // phpcs:ignore
    public static function validateLimit(mixed $option, mixed $old, mixed $new): void
    {
        if ($option !== Limit::getName()) {
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

        $paymentMethod = Repository::getById(paymentMethodId: $paymentMethodId);

        if ($paymentMethod === null) {
            MessageBag::addError(message: Translator::translate(
                phraseId: 'limit-failed-to-load-payment-method'
            ));
            return;
        }

        $maxLimit = $paymentMethod->maxPurchaseLimit;
        $customerCountry = get_option('woocommerce_default_country');
        $minLimit = 150;

        if ($customerCountry === 'FI') {
            $minLimit = 15;
        }

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
     * Raw values when saving must be either uuid or integers.
     */
    private static function isValidRawValue(string $rawValue): bool
    {
        try {
            $stringValidation = new StringValidation();
            $isUuid = $stringValidation->isUuid(value: $rawValue);
        } catch (ValidationException) {
            $isUuid = false;
        }

        return $isUuid || (int)$rawValue > 0;
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
            'type' => 'rbpartpaymentperiod',
            'default' => Period::getDefault(),
            'options' => self::getAnnuityPeriods(),
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

    /**
     * Fetch annuity period options for configured payment method
     */
    private static function getAnnuityPeriods(): array
    {
        $storeId = StoreId::getData();
        $paymentMethodId = PaymentMethodOption::getData();

        $annuityFactors = [];
        $return = [
            '' => Translator::translate(phraseId: 'please-select'),
        ];

        try {
            if ($paymentMethodId !== '' && $storeId !== '') {
                $annuityFactors = AnnuityRepository::getAnnuityFactors(
                    paymentMethodId: $paymentMethodId
                )->getData();
            }
        } catch (Throwable) {
            MessageBag::addError(
                message: Translator::translate(
                    phraseId: 'get-annuity-periods-failed'
                )
            );
        }

        foreach ($annuityFactors as $annuityFactor) {
            $return[$annuityFactor->durationMonths] = $annuityFactor->paymentPlanName;
        }

        return $return;
    }
}
