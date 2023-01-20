<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use Exception;
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
use Resursbank\Ecom\Lib\Locale\Translator;
use Resursbank\Ecom\Module\AnnuityFactor\Repository as AnnuityRepository;
use Resursbank\Ecom\Module\PaymentMethod\Repository;
use ResursBank\Module\Data;
use ResursBank\Service\WordPress;
use Resursbank\Woocommerce\Database\Options\PartPayment\Enabled;
use Resursbank\Woocommerce\Database\Options\PartPayment\Limit;
use Resursbank\Woocommerce\Database\Options\PartPayment\PaymentMethod as PaymentMethodOption;
use Resursbank\Woocommerce\Database\Options\PartPayment\Period;
use Resursbank\Woocommerce\Database\Options\StoreId;
use Throwable;

/**
 * Generates the settings form for the Part payment module
 */
class PartPayment
{
    public const SECTION_ID = 'partpayment';
    public const SECTION_TITLE = 'Part payment';

    /**
     * @return array<array>
     * @throws ConfigException
     * @throws EmptyValueException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     * @throws ValidationException
     */
    public static function getSettings(): array
    {
        return [
            self::SECTION_ID => [
                'title' => self::SECTION_TITLE,
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
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     * @throws ValidationException
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @noinspection PhpUnusedParameterInspection
     * @todo Refactor, method is too large, complex, unused. WOO-980. Remove phpcs:ignore below when done.
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
            WordPress::setGenericError(
                exception: new Exception(
                    message: Translator::translate(
                        phraseId: 'limit-missing-store-id'
                    )
                )
            );
            return;
        }

        if (empty($paymentMethodId)) {
            WordPress::setGenericError(
                exception: new Exception(
                    message: Translator::translate(
                        phraseId: 'limit-missing-payment-method'
                    )
                )
            );
            return;
        }

        if (empty($period)) {
            WordPress::setGenericError(
                exception: new Exception(
                    message: Translator::translate(
                        phraseId: 'limit-missing-period'
                    )
                )
            );
            return;
        }

        $paymentMethod = Repository::getById(
            storeId: $storeId,
            paymentMethodId: $paymentMethodId
        );

        if ($paymentMethod === null) {
            WordPress::setGenericError(
                exception: new Exception(
                    message: Translator::translate(
                        phraseId: 'limit-failed-to-load-payment-method'
                    )
                )
            );
            return;
        }

        $maxLimit = $paymentMethod->maxPurchaseLimit;

        // @todo Find a better solution for this
        $customerCountry = Data::getCustomerCountry();
        $minLimit = 150;

        if ($customerCountry === 'FI') {
            $minLimit = 15;
        }

        if ($new < 0) {
            WordPress::setGenericError(
                exception: new Exception(
                    message: Translator::translate(
                        phraseId: 'limit-new-value-not-positive'
                    )
                )
            );
        } elseif ($new > $maxLimit) {
            WordPress::setGenericError(
                exception: new Exception(
                    message: str_replace(
                        search: '%1',
                        replace: (string)$maxLimit,
                        subject: Translator::translate(
                            phraseId: 'limit-new-value-above-max'
                        )
                    )
                )
            );
        } elseif ($new < $minLimit) {
            WordPress::setGenericError(
                exception: new Exception(
                    message: str_replace(
                        search: '%1',
                        replace: (string)$minLimit,
                        subject: Translator::translate(
                            phraseId: 'limit-new-value-below-min'
                        )
                    )
                )
            );
        }
    }

    /**
     * Fetches the enabled setting.
     *
     * @throws ConfigException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
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
     *
     * @throws ConfigException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     */
    private static function getPaymentMethodSetting(): array
    {
        return [
            'id' => PaymentMethodOption::getName(),
            'title' => Translator::translate(phraseId: 'payment-method'),
            'type' => 'select',
            'default' => PaymentMethodOption::getDefault(),
            'options' => self::getPaymentMethods(),
            'desc' => Translator::translate(
                phraseId: 'part-payment-payment-method'
            ),
        ];
    }

    /**
     * Fetches the period setting.
     *
     * @throws ConfigException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     */
    private static function getPeriodSetting(): array
    {
        return [
            'id' => Period::getName(),
            'title' => Translator::translate(phraseId: 'annuity-period'),
            'type' => 'select',
            'default' => Period::getDefault(),
            'options' => self::getAnnuityPeriods(),
            'desc' => Translator::translate(
                phraseId: 'part-payment-annuity-period'
            ),
        ];
    }

    /**
     * Fetches the limit setting.
     *
     * @throws ConfigException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
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
     * Fetch available payment method options
     *
     * @return array
     */
    private static function getPaymentMethods(): array
    {
        $storeId = StoreId::getData();
        $paymentMethods = [];
        $return = [];

        try {
            $paymentMethods = $storeId !== '' ?
                Repository::getPaymentMethods(storeId: $storeId) : [];
        } catch (Throwable $exception) {
            WordPress::setGenericError(exception: $exception);
        }

        foreach ($paymentMethods as $paymentMethod) {
            if (!$paymentMethod->isPartPayment()) {
                continue;
            }

            $return[$paymentMethod->id] = $paymentMethod->name;
        }

        return $return;
    }

    /**
     * Fetch annuity period options for configured payment method
     *
     * @return array
     */
    private static function getAnnuityPeriods(): array
    {
        $paymentMethodId = PaymentMethodOption::getData();
        $storeId = StoreId::getData();
        $annuityFactors = [];
        $return = [];

        try {
            if ($paymentMethodId !== '' && $storeId !== '') {
                $annuityFactors = AnnuityRepository::getAnnuityFactors(
                    storeId: $storeId,
                    paymentMethodId: $paymentMethodId
                )->content;
            }
        } catch (Throwable $exception) {
            WordPress::setGenericError(exception: $exception);
        }

        foreach ($annuityFactors as $annuityFactor) {
            $return[$annuityFactor->durationMonths] = $annuityFactor->paymentPlanName;
        }

        return $return;
    }
}
