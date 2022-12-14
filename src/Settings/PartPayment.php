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
use Resursbank\Ecom\Lib\Model\PaymentMethod;
use Resursbank\Ecom\Module\AnnuityFactor\Models\AnnuityInformation;
use Resursbank\Ecom\Module\PaymentMethod\Repository;
use Resursbank\Ecom\Module\AnnuityFactor\Repository as AnnuityRepository;
use ResursBank\Service\WordPress;
use Resursbank\Woocommerce\Database\Options\PartPayment\Enabled;
use Resursbank\Woocommerce\Database\Options\PartPayment\PaymentMethod as PaymentMethodOption;
use Resursbank\Woocommerce\Database\Options\PartPayment\Period;
use Resursbank\Woocommerce\Database\Options\StoreId;

/**
 * Generates the settings form for the Part payment module
 */
class PartPayment
{
    public const SECTION_ID = 'partpayment';
    public const SECTION_TITLE = 'Part payment';

    /**
     * @return array[]
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
     */
    public static function getSettings(): array
    {
        return [
            self::SECTION_ID => [
                'title'          => self::SECTION_TITLE,
                'enabled'        => [
                    'id'      => Enabled::getName(),
                    'title'   => Translator::translate(phraseId: 'part-payment-widget-enabled'),
                    'type'    => 'checkbox',
                    'default' => 'no',
                    'desc'    => 'Enabled'
                ],
                'payment_method' => [
                    'id'      => PaymentMethodOption::getName(),
                    'title'   => Translator::translate(phraseId: 'payment-method'),
                    'type'    => 'select',
                    'options' => self::getPaymentMethods()
                ],
                'period'         => [
                    'id'      => Period::getName(),
                    'title'   => Translator::translate(phraseId: 'annuity-period'),
                    'type'    => 'select',
                    'options' => self::getAnnuityPeriods()
                ]
            ]
        ];
    }

    /**
     * Fetch available payment method options
     *
     * @return array
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AuthException
     * @throws CacheException
     * @throws ConfigException
     * @throws CurlException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     */
    private static function getPaymentMethods(): array
    {
        $storeId = StoreId::getData();

        if (empty($storeId)) {
            return [];
        }

        $options = [];
        try {
            $paymentMethods = Repository::getPaymentMethods(storeId: $storeId);
        } catch (Exception $exception) {
            WordPress::setGenericError(exception: $exception);
        }

        if (isset($paymentMethods)) {
            /** @var PaymentMethod $paymentMethod */
            foreach ($paymentMethods as $paymentMethod) {
                if ($paymentMethod->isPartPayment()) {
                    $options[$paymentMethod->id] = $paymentMethod->name;
                }
            }
        }

        return $options;
    }

    /**
     * Fetch annuity period options for configured payment method
     *
     * @return array
     * @throws ConfigException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ValidationException
     * @throws FilesystemException
     * @throws TranslationException
     */
    private static function getAnnuityPeriods(): array
    {
        $paymentMethodId = PaymentMethodOption::getData();
        $storeId = StoreId::getData();

        if (empty($paymentMethodId) || empty($storeId)) {
            return [];
        }

        try {
            $annuityFactors = AnnuityRepository::getAnnuityFactors(
                storeId: StoreId::getData(),
                paymentMethodId: $paymentMethodId
            );
        } catch (Exception $exception) {
            WordPress::setGenericError(exception: $exception);
        }

        $return = [];

        if (isset($annuityFactors)) {
            /** @var AnnuityInformation $annuityFactor */
            foreach ($annuityFactors->content as $annuityFactor) {
                $return[$annuityFactor->durationInMonths] = $annuityFactor->durationInMonths . ' ' .
                                                            Translator::translate(phraseId: 'months');
            }
        }

        return $return;
    }
}
