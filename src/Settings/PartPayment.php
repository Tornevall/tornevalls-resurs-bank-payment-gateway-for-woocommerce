<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Lib\UserSettings\Field;
use Resursbank\Ecom\Module\AnnuityFactor\Repository;
use Resursbank\Ecom\Module\PaymentMethod\Repository as PaymentMethodRepository;
use Resursbank\Ecom\Module\UserSettings\Repository as UserSettingsRepository;
use Resursbank\Woocommerce\Modules\UserSettings\Reader;
use Resursbank\Woocommerce\Util\Translator;

/**
 * Generates the settings form for the Part payment module.
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
            'options' => Repository::getAssocPaymentMethods(),
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
            'options' => Repository::getUniquePeriodsAsAssoc(),
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
