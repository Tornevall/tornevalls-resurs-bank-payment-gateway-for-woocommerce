<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\PaymentMethod\Widget;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Lib\Locale\Translator;
use Resursbank\Ecom\Lib\Widget\Widget;
use Resursbank\Ecom\Module\PaymentMethod\Models\PaymentMethodCollection;

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
    public readonly string $missingMethodsWarning;

    /**
     * @param PaymentMethodCollection $paymentMethods
     * @throws JsonException
     * @throws ReflectionException
     * @throws FilesystemException
     * @throws TranslationException
     * @throws IllegalTypeException
     */
    public function __construct(
        public readonly PaymentMethodCollection $paymentMethods,
    ) {
        $this->nameLabel = Translator::translate(phraseId: 'name');
        $this->minTotalLabel = Translator::translate(phraseId: 'min-total');
        $this->maxTotalLabel = Translator::translate(phraseId: 'max-total');
        $this->sortOrderLabel = Translator::translate(phraseId: 'sort-order');
        $this->missingMethodsWarning = Translator::translate(phraseId: 'no-payment-methods');
        $this->content = $this->render(file: __DIR__ . '/payment-methods.phtml');
    }
}
