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
use Resursbank\Ecom\Module\PaymentMethod\Models\PaymentMethod;

/**
 * Read more widget.
 */
class ReadMore extends Widget
{
    /**
     * @var string
     */
    public string $url = '';

    /**
     * @var string
     */
    public readonly string $content;

    /**
     * @var string
     */
    public readonly string $label;

    /**
     * @param PaymentMethod $paymentMethod
     * @param float $amount
     * @throws FilesystemException
     * @throws JsonException
     * @throws ReflectionException
     * @throws IllegalTypeException
     * @throws TranslationException
     */
    public function __construct(
        public readonly PaymentMethod $paymentMethod,
        public readonly float $amount
    ) {
        foreach ($this->paymentMethod->legalLinks as $link) {
            if ($link->type === 'PRICE_INFO') {
                $this->url = $link->url;
            }
        }

        $this->label = Translator::translate(phraseId: 'read-more');
        $this->content = $this->render(file: __DIR__ . '/read-more.phtml');
    }
}
