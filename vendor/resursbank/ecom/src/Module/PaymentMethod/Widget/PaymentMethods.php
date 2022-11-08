<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\PaymentMethod\Widget;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Lib\Locale\Translator;
use Resursbank\Ecom\Lib\Widget\Widget;
use Resursbank\Ecom\Lib\Model\PaymentMethodCollection;

/**
 * Payment methods table widget.
 */
class PaymentMethods extends Widget
{
    /**
     * @var string
     */
    public readonly string $content;

    /**
     * @var string
     */
    public readonly string $nameLabel;

    /**
     * @var string
     */
    public readonly string $minTotalLabel;

    /**
     * @var string
     */
    public readonly string $maxTotalLabel;

    /**
     * @var string
     */
    public readonly string $sortOrderLabel;

    /**
     * @var string
     */
    public readonly string $missingWarning;

    /**
     * @param PaymentMethodCollection $paymentMethods
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     * @throws ConfigException
     */
    public function __construct(
        public readonly PaymentMethodCollection $paymentMethods,
    ) {
        $this->nameLabel = Translator::translate(phraseId: 'name');
        $this->minTotalLabel = Translator::translate(phraseId: 'min-total');
        $this->maxTotalLabel = Translator::translate(phraseId: 'max-total');
        $this->sortOrderLabel = Translator::translate(phraseId: 'sort-order');
        $this->missingWarning = Translator::translate(phraseId: 'no-payment-methods');
        $this->content = $this->render(file: __DIR__ . '/payment-methods.phtml');
    }
}
