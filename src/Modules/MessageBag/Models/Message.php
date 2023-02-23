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
use Resursbank\Woocommerce\Util\Sanitize;

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
        public readonly string $message,
        public readonly Type $type,
        private readonly StringValidation $stringValidation = new StringValidation()
    ) {
        $this->validateMessage();
    }

    /**
     * Retrieved escaped message for rendering.
     */
    public function getEscapedMessage(): string
    {
        return Sanitize::sanitizeHtml(html: $this->message);
    }

    /**
     * Ensure message is not empty.
     *
     * @throws EmptyValueException
     */
    private function validateMessage(): void
    {
        $this->stringValidation->notEmpty(value: $this->message);
    }
}
