<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\PartPayment\Controller;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\CacheException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Module\PaymentMethod\Repository;
use Resursbank\Ecom\Module\PaymentMethod\Widget\PartPayment as PartPaymentWidget;
use Resursbank\Ecom\Module\PaymentMethod\Widget\ReadMore;
use Resursbank\Woocommerce\Database\Options\Advanced\StoreId;
use Resursbank\Woocommerce\Database\Options\PartPayment\Limit;
use Resursbank\Woocommerce\Database\Options\PartPayment\PaymentMethod;
use Resursbank\Woocommerce\Database\Options\PartPayment\Period;
use Resursbank\Woocommerce\Util\Currency;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Url;
use Throwable;

/**
 * AJAX controller for the Part payment widget
 */
class PartPayment
{
    /**
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
     * @throws HttpException
     * @throws Throwable
     */
    public static function exec(): string
    {
        $response = [
            'css' => '',
            'startingAt' => '',
            'startingAtHtml' => '',
            'readMoreWidget' => '',
            'monthlyCost' => ''
        ];

        $paymentMethod = Repository::getById(
            paymentMethodId: PaymentMethod::getData()
        );

        $requestAmount = Url::getHttpJson(key: 'amount');

        if (
            is_numeric(value: $requestAmount) &&
            $paymentMethod !== null
        ) {
            $currencySymbol = Currency::getWooCommerceCurrencySymbol();
            $widget = new PartPaymentWidget(
                storeId: StoreId::getData(),
                paymentMethod: $paymentMethod,
                months: (int)Period::getData(),
                amount: (float)$requestAmount,
                currencySymbol: $currencySymbol,
                currencyFormat: Currency::getEcomCurrencyFormat(),
                fetchStartingCostUrl: Route::getUrl(
                    route: Route::ROUTE_PART_PAYMENT
                ),
                threshold: Limit::getData()
            );
            $readMoreWidget = new ReadMore(
                paymentMethod: $paymentMethod,
                amount: (float)$requestAmount
            );
            $response['startingAtHtml'] = $widget->getStartingAt();
            $response['startingAt'] = (float)$requestAmount;
            $response['readMoreWidget'] = $readMoreWidget->content;
            $response['monthlyCost'] = $widget->getMonthlyCost();
        }

        try {
            return json_encode(
                value: $response,
                flags: JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR
            );
        } catch (Throwable) {
            return '';
        }
    }
}
