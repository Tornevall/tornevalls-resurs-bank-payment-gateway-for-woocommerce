<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\PaymentMethod\Widget;

use Resursbank\Ecom\Exception\FilesystemException;
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
     * @param PaymentMethod $paymentMethod
     * @param float $amount
     * @param string $label
     * @throws FilesystemException
     */
    public function __construct(
        public readonly PaymentMethod $paymentMethod,
        public readonly float $amount,
        public readonly string $label = 'Read more'
    ) {
        foreach ($this->paymentMethod->legalLinks as $link) {
            if ($link->type === 'PRICE_INFO') {
                $this->url = $link->url;
            }
        }

        echo $this->render(file: __DIR__ . '/read-more.phtml');
    }
}
