<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\PartPayment\Controller\Admin;

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
use Resursbank\Ecom\Lib\Validation\StringValidation;
use Resursbank\Ecom\Module\AnnuityFactor\Models\AnnuityInformation;
use Resursbank\Ecom\Module\AnnuityFactor\Repository;
use ResursBank\Service\WordPress;
use Resursbank\Woocommerce\Database\Options\StoreId;

/**
 * Controller for fetching valid duration options for a specified payment method
 */
class GetValidDurations
{
    /**
     * @throws TranslationException
     * @throws ValidationException
     * @throws CurlException
     * @throws IllegalValueException
     * @throws IllegalTypeException
     * @throws AuthException
     * @throws EmptyValueException
     * @throws JsonException
     * @throws ConfigException
     * @throws ReflectionException
     * @throws ApiException
     * @throws CacheException
     * @throws FilesystemException
     */
    public static function exec(): string
    {
        $stringValidation = new StringValidation();
        $paymentMethodId = sanitize_text_field(str: $_GET['paymentMethodId']);
        $storeId = StoreId::getData();
        $return = [];

        try {
            if ($storeId === '') {
                throw new IllegalValueException(message: 'No storeId available');
            }
            $stringValidation->isUuid(value: $paymentMethodId);

            $annuityFactors = Repository::getAnnuityFactors(
                storeId: $storeId,
                paymentMethodId: $paymentMethodId
            );

            /** @var AnnuityInformation $annuityFactor */
            foreach ($annuityFactors->content as $annuityFactor) {
                $return[$annuityFactor->durationInMonths] = $annuityFactor->paymentPlanName;
            }
        } catch (Exception $exception) {
            WordPress::setGenericError(exception: $exception);
            throw $exception;
        }

        return json_encode(
            value: $return,
            flags: JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT
        );
    }
}
