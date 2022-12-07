<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\PartPayment\Controller\Admin;

use Exception;
use Resursbank\Ecom\Lib\Locale\Translator;
use Resursbank\Ecom\Lib\Validation\StringValidation;
use Resursbank\Ecom\Module\AnnuityFactor\Models\AnnuityInformation;
use Resursbank\Ecom\Module\AnnuityFactor\Repository;
use ResursBank\Service\WordPress;
use Resursbank\Woocommerce\Database\Options\StoreId;

class GetValidDurations
{
    /**
     * @throws \Resursbank\Ecom\Exception\TranslationException
     * @throws \Resursbank\Ecom\Exception\ValidationException
     * @throws \Resursbank\Ecom\Exception\CurlException
     * @throws \Resursbank\Ecom\Exception\Validation\IllegalValueException
     * @throws \Resursbank\Ecom\Exception\Validation\IllegalTypeException
     * @throws \Resursbank\Ecom\Exception\AuthException
     * @throws \Resursbank\Ecom\Exception\Validation\EmptyValueException
     * @throws \JsonException
     * @throws \Resursbank\Ecom\Exception\ConfigException
     * @throws \ReflectionException
     * @throws \Resursbank\Ecom\Exception\ApiException
     * @throws \Resursbank\Ecom\Exception\CacheException
     * @throws \Resursbank\Ecom\Exception\FilesystemException
     */
    public static function exec(): string
    {
        $stringValidation = new StringValidation();
        $paymentMethodId = sanitize_text_field(str: $_GET['paymentMethodId']);
        $storeId = StoreId::getData();
        $return = [];

        if (!empty($storeId) && $stringValidation->isUuid(value: $paymentMethodId)) {
            try {
                $annuityFactors = Repository::getAnnuityFactors(
                    storeId: $storeId,
                    paymentMethodId: $paymentMethodId
                );
            } catch (Exception $exception) {
                WordPress::setGenericError(exception: $exception);
                throw $exception;
            }

            /** @var AnnuityInformation $annuityFactor */
            foreach ($annuityFactors->content as $annuityFactor) {
                $return[$annuityFactor->durationInMonths] = $annuityFactor->durationInMonths . ' ' .
                                                            Translator::translate(phraseId: 'months');
            }
        }

        return json_encode(
            value: $return,
            flags: JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT
        );
    }
}
