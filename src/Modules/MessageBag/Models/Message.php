<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\MessageBag\Models;

use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Lib\Validation\StringValidation;
use Resursbank\Woocommerce\Modules\MessageBag\Type;

/**
 * Message definition.
 */
class Message extends Model
{
    /**
     * Setup model properties.
     *
     * @throws EmptyValueException
     */
    public function __construct(
        public readonly string $msg,
        public readonly Type $type,
        private readonly StringValidation $stringValidation = new StringValidation()
    ) {
        $this->validateMsg();
    }

    /**
     * Retrieved escaped message for rendering.
     */
    public function getEscapedMsg(): string
    {
        return (string) esc_html(text: $this->msg);
    }

    /**
     * Ensure message is not empty.
     *
     * @throws EmptyValueException
     */
    private function validateMsg(): void
    {
        $this->stringValidation->notEmpty(value: $this->msg);
    }
}
