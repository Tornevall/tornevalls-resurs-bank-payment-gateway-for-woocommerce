<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\CacheException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\UserSettingsException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Model\PaymentMethodCollection;
use Resursbank\Ecom\Lib\UserSettings\Field;
use Resursbank\Ecom\Lib\Validation\StringValidation;
use Resursbank\Ecom\Module\PaymentMethod\Repository;
use Resursbank\Ecom\Module\Store\Repository as StoreRepository;
use Resursbank\Ecom\Module\UserSettings\Repository as UserSettingsRepository;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Modules\UserSettings\Reader;
use Resursbank\Woocommerce\Util\Translator;
use Resursbank\Woocommerce\Util\WooCommerce;
use Throwable;

/**
 * Generates the settings form for the Part payment module.
 *
 * @noinspection PhpClassHasTooManyDeclaredMembersInspection
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
     * @todo I can see a reason for validating min limit, but not ppw setting and it's meshed together with store, remove this validation. min limit should for that matter by validated on frontend at input time, not by the backend.
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
     * @todo We present the select list, this is unnecessary. If anyone circumvents our system to store in inaccurate value they will just end up with Ecom not working anyway. This should be removed completely.
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
     * Validate Limit setting and show error messages if the user hasn't configured the widget correctly.
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
     * @noinspection PhpUnusedParameterInspection
     * @todo Should be moved to ecom and to the frontend. If anyone goes around it let them. If we want to validate it's accurate on the backend we should do so in ecom, not here.
     */
    // phpcs:ignore
    public static function validateLimit(mixed $option, mixed $old, mixed $new): void
    {
        if (!self::canValidateOptionChange($option)) {
            return;
        }

        if (
            $option === 'woocommerce_currency' ||
            $option === 'woocommerce_currency_pos'
        ) {
            WooCommerce::invalidateFullCache();
            return;
        }

        if ($option === Reader::getOptionName(field: Field::STORE_ID)) {
            // Store the NEW value, not the value already stored in the system!
            PartPayment::handleStoreIdUpdate(newStoreId: $new);
            return;
        }

        if (!self::validateStoreAndMethod()) {
            return;
        }

        self::handleLimitUpdate(new: $new);
    }

    /**
     * Handles the update logic when StoreId changes.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     * @todo min limit validation should be handled by ECom.
     */
    public static function handleStoreIdUpdate(mixed $newStoreId): void
    {
        global $overrideSavedCountryCode, $isCountryOverride;

        $isCountryOverride = false;

        try {
            Config::getCache()->invalidate();

            $newStore = StoreRepository::getStores()->filterById(
                id: $newStoreId
            );

            try {
                $paymentMethods = Repository::getPaymentMethods();
                WooCommerce::updatePartPaymentData(
                    paymentMethods: $paymentMethods
                );

                self::updateLongestPeriodWithZeroInterest(
                    paymentMethods: $paymentMethods
                );
            } catch (Throwable) {
                // Ignore exception and proceed with no further actions.
            }

            $countryCode = $newStore->countryCode->value;
            $currentStoreCountry = WooCommerce::getStoreCountry();

            if (
                self::canSwitchMinValueByCountry(
                    $currentStoreCountry,
                    $countryCode
                )
            ) {
                $overrideSavedCountryCode = $countryCode;
                $isCountryOverride = true;
            }
        } catch (Throwable) {
            // Ignore exception.
        }
    }

    /**
     * Determines if this option should be validated.
     *
     * @todo I've no idea what this is for, documentation needs improvement. This is only called from validateLimit, no idea what these other settings have to do with that.
     */
    private static function canValidateOptionChange(mixed $option): bool
    {
        return $option === Reader::getOptionName(field: Field::PART_PAYMENT_THRESHOLD) ||
            $option === Reader::getOptionName(field: Field::STORE_ID)||
            $option === 'woocommerce_currency' ||
            $option === 'woocommerce_currency_pos';
    }

    /**
     * Checks if required store, payment method, and period data is present.
     *  If missing, an error message is added and false is returned.
     *
     * @throws ConfigException
     * @throws UserSettingsException
     * @todo Remove this with the rest of the validation.
     *
     */
    private static function validateStoreAndMethod(): bool
    {
        global $isCountryOverride;

        $settings = UserSettingsRepository::getSettings();
        $paymentMethodId = $settings->partPaymentMethod?->id;
        $storeId = Config::getStoreId();
        $period = $settings->partPaymentPeriod;

        if (empty($storeId)) {
            MessageBag::addError(message: Translator::translate(
                phraseId: 'limit-missing-store-id'
            ));
            return false;
        }

        if (empty($paymentMethodId)) {
            MessageBag::addError(message: Translator::translate(
                phraseId: 'limit-missing-payment-method'
            ));
            return false;
        }

        // Country overrider may cause empty values during a limited amount of
        // time due to handleStoreIdUpdate are saving the threshold values via
        // the update hook. During this time we should not validate the period.
        if (empty($period) && !$isCountryOverride) {
            MessageBag::addError(message: Translator::translate(
                phraseId: 'limit-missing-period'
            ));
            return false;
        }

        return true;
    }

    /**
     * When we save new stores to the configuration, the payment method will no longer match with the PPW rules.
     *   This method will update the longest period with zero interest for the new payment method and save the uuid.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     * @todo As this method already exists in ecom it eventually could be better to centralize it?
     */
    private static function updateLongestPeriodWithZeroInterest(PaymentMethodCollection $paymentMethods): void
    {
        try {
            // This method is triggered through several requests due to how javascript are loaded
            // but should not be fully executed when AJAX requests are handling the calls.
            $isAjaxRequest = isset($_REQUEST['resursbank']) && $_REQUEST['resursbank'] === 'get-store-country';

            if ($isAjaxRequest) {
                return;
            }

            WooCommerce::updatePartPaymentData(paymentMethods: $paymentMethods);
        } catch (Throwable) {
            // Ignore exceptions and proceed if this moment fails, and leave the configuration as is instead
            // of blocking something.
        }
    }

    /**
     * Checks if the store change is a cross-country change.
     *
     * @todo This should either not exist or be placed in ECom.
     */
    private static function canSwitchMinValueByCountry(string $currentCountry, string $newCountry): bool
    {
        $countryGroup1 = ['SE', 'NO', 'DK'];
        $countryGroup2 = ['FI'];

        return
            (in_array(
                needle: $currentCountry,
                haystack: $countryGroup1,
                strict: true
            ) && in_array(
                needle: $newCountry,
                haystack: $countryGroup2,
                strict: true
            )) ||
            (in_array(
                needle: $currentCountry,
                haystack: $countryGroup2,
                strict: true
            ) && in_array(
                needle: $newCountry,
                haystack: $countryGroup1,
                strict: true
            ));
    }

    /**
     * Handles the validation of the limit when StoreId is not the updated option.
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
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     * @todo This seems again to be a lot of validation that should, if exist at all, exist in ECom, and also it seems we are just using our own constants anyway so, not sure why this is here at all.
     */
    private static function handleLimitUpdate(mixed $new): void
    {
        global $overrideSavedCountryCode;

        $customerCountry = get_option('woocommerce_default_country');

        try {
            $storeCountry = WooCommerce::getStoreCountry() ?? $customerCountry;

            // Do not touch anything if override is active.
            if (isset($overrideSavedCountryCode)) {
                return;
            }
        } catch (Throwable) {
            $storeCountry = $customerCountry;
        }

        $paymentMethod = UserSettingsRepository::getSettings()->partPaymentMethod;

        if ($paymentMethod === null) {
            MessageBag::addError(message: Translator::translate(
                phraseId: 'limit-failed-to-load-payment-method'
            ));
            return;
        }

        $maxLimit = $paymentMethod->maxPurchaseLimit;
        $minLimit = (float)($storeCountry === 'FI')
            ? self::MINIMUM_THRESHOLD_LIMIT_FI
            : self::MINIMUM_THRESHOLD_LIMIT_DEFAULT;

        // Validate numeric range against min/max.
        if ($new < 0) {
            MessageBag::addError(message: Translator::translate(
                phraseId: 'limit-new-value-not-positive'
            ));
        } elseif ((float)$new > $maxLimit) {
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
     * @todo We give the options, not sure why we would validate the input like this? If someone goes around us Ecom will error anyway, so why bother with this?
     */
    private static function isValidPeriodOption(mixed $option, mixed $raw_value): bool
    {
        return $option['id'] === Reader::getOptionName(field: Field::PART_PAYMENT_PERIOD) && (int)$raw_value > 0;
    }

    /**
     * @SuppressWarnings(PHPMD.CamelCaseMethodName)
     * @SuppressWarnings(PHPMD.CamelCaseVariableName)
     * @SuppressWarnings(PHPMD.CamelCaseParameterName)
     * @todo UUID validation is already supplied by Ecom, this must go.
     */
    private static function isValidPaymentOrStoreIdOption(mixed $option, mixed $raw_value): bool
    {
        if (
            $raw_value !== '' &&
            (
                $option['id'] === Reader::getOptionName(field: Field::PART_PAYMENT_METHOD) ||
                $option['id'] === Reader::getOptionName(field: Field::STORE_ID)
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
     * @todo Just a pointless shortcut to ECom, remove.
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
            'id' => Reader::getOptionName(field: Field::PART_PAYMENT_ENABLED),
            'title' => Translator::translate(
                phraseId: 'part-payment-widget-enabled'
            ),
            'type' => 'checkbox',
            'default' => UserSettingsRepository::getDefault(field: Field::PART_PAYMENT_ENABLED) ? 'yes' : 'no',
        ];
    }

    /**
     * Fetches the payment_method setting.
     *
     * @throws ConfigException
     */
    private static function getPaymentMethodSetting(): array
    {
        return [
            'id' => Reader::getOptionName(field: Field::PART_PAYMENT_METHOD),
            'title' => Translator::translate(phraseId: 'payment-method'),
            'type' => 'select',
            'default' => UserSettingsRepository::getDefault(field: Field::PART_PAYMENT_METHOD),
            'options' => [],
            'desc' => Translator::translate(
                phraseId: 'part-payment-payment-method'
            ),
        ];
    }

    /**
     * Fetches the period setting.
     *
     * @throws ConfigException
     */
    private static function getPeriodSetting(): array
    {
        return [
            'id' => Reader::getOptionName(field: Field::PART_PAYMENT_PERIOD),
            'title' => Translator::translate(phraseId: 'annuity-period'),
            'type' => 'select',
            'default' => (string) UserSettingsRepository::getDefault(field: Field::PART_PAYMENT_PERIOD),
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
            'id' => Reader::getOptionName(field: Field::PART_PAYMENT_THRESHOLD),
            'title' => Translator::translate(phraseId: 'limit'),
            'type' => 'text',
            'default' => UserSettingsRepository::getDefaultPartPaymentThreshold(),
            'desc' => Translator::translate(phraseId: 'part-payment-limit'),
        ];
    }
}
