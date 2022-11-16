<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\PaymentMethod\Widget;

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
use Resursbank\Ecom\Lib\Widget\Widget;
use Resursbank\Ecom\Module\AnnuityFactor\Models\AnnuityInformation;
use Resursbank\Ecom\Lib\Order\PaymentMethod\LegalLink\Type as LegalLinkType;
use Resursbank\Ecom\Module\AnnuityFactor\Repository;

/**
 * Renders Part payment widget HTML and CSS
 */
class PartPayment extends Widget
{
    /** @var string  */
    public readonly string $logo;

    /** @var string  */
    public readonly string $infoText;

    /** @var string  */
    public readonly string $content;

    /** @var string  */
    public readonly string $css;

    /** @var string  */
    public readonly string $readMore;

    /** @var string  */
    public readonly string $iframeUrl;

    /** @var string  */
    public readonly string $startingAt;

    /** @var AnnuityInformation */
    private readonly AnnuityInformation $annuity;

    /**
     * @param string $storeId
     * @param PaymentMethod $paymentMethod
     * @param int $months
     * @param float $amount
     * @throws ConfigException
     * @throws EmptyValueException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     * @throws ApiException
     * @throws AuthException
     * @throws CacheException
     * @throws CurlException
     * @throws ValidationException
     * @throws IllegalValueException
     */
    public function __construct(
        private readonly string $storeId,
        private readonly PaymentMethod $paymentMethod,
        private readonly int $months,
        private readonly float $amount
    ) {
        $this->annuity = $this->getAnnuityFactor();
        $this->logo = file_get_contents(filename: __DIR__ . '/resurs.svg');
        $this->infoText = Translator::translate(phraseId: 'pay-in-installments-with-resurs-bank');
        $this->startingAt = $this->getStartingAt();
        $this->readMore = Translator::translate(phraseId: 'read-more');
        $this->iframeUrl = $this->getIframeUrl();

        $this->content = $this->render(file: __DIR__ . '/part-payment.phtml');
        $this->css = $this->render(file: __DIR__ . '/part-payment.css');
    }

    /**
     * Fetches the relevant annuity factor
     *
     * @return AnnuityInformation
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
     */
    private function getAnnuityFactor(): AnnuityInformation
    {
        $annuityFactors = Repository::getAnnuityFactors(
            storeId: $this->storeId,
            paymentMethodId: $this->paymentMethod->id
        );

        $annuity = null;
        /** @var AnnuityInformation $annuityFactor */
        foreach ($annuityFactors->content as $annuityFactor) {
            if ($annuityFactor->durationInMonths === $this->months) {
                $annuity = $annuityFactor;
                break;
            }
        }
        if (!$annuity) {
            throw new EmptyValueException(message: 'Unable to find matching annuity information object');
        }

        return $annuity;
    }

    /**
     * Fetches translated and formatted "Starting at %1 per month..." string
     *
     * @return string
     * @throws ConfigException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     */
    private function getStartingAt(): string
    {
        return str_replace(
            search: ['%1', '%2'],
            replace: [
                (string)(round(
                    num: $this->amount * $this->annuity->annuityFactor + $this->annuity->monthlyAdminFee,
                    precision: 2
                )),
                (string)$this->annuity->durationInMonths
            ],
            subject: Translator::translate(phraseId: 'starting-at')
        );
    }

    /**
     * Fetches iframe URL
     *
     * @return string
     * @todo: Properly render URL
     */
    private function getIframeUrl(): string
    {
        /** @var PaymentMethod\LegalLink $legalLink */
        foreach ($this->paymentMethod->legalLinks as $legalLink) {
            if ($legalLink->type === LegalLinkType::PRICE_INFO) {
                return $legalLink->url . $this->amount;
            }
        }
        return '';
    }
}
