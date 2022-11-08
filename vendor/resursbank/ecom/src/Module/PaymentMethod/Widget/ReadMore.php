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
use Resursbank\Ecom\Lib\Model\PaymentMethod\LegalLink;
use Resursbank\Ecom\Lib\Order\PaymentMethod\LegalLink\Type;
use Resursbank\Ecom\Lib\Widget\Widget;
use Resursbank\Ecom\Lib\Model\PaymentMethod;

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

    /** @var string */
    public readonly string $css;

    /**
     * @var string
     */
    public readonly string $label;

    /**
     * @param PaymentMethod $paymentMethod
     * @param float $amount
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     * @throws ConfigException
     */
    public function __construct(
        public readonly PaymentMethod $paymentMethod,
        public readonly float $amount
    ) {
        /** @var LegalLink $link */
        foreach ($this->paymentMethod->legalLinks as $link) {
            if ($link->type === Type::PRICE_INFO) {
                $this->url = $link->url;
            }
        }

        $this->label = Translator::translate(phraseId: 'read-more');
        $this->content = $this->render(file: __DIR__ . '/read-more.phtml');
        $this->css = $this->render(file: __DIR__ . '/read-more.css');
    }
}
