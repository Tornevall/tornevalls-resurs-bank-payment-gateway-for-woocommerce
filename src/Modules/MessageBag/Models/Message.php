<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\MessageBag\Models;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\AttributeCombinationException;
use Resursbank\Ecom\Lib\Attribute\Validation\StringNotEmpty;
use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Woocommerce\Modules\MessageBag\Type;

/**
 * Message definition.
 */
class Message extends Model
{
    /**
     * Setup model properties.
     *
     * @throws AttributeCombinationException
     * @throws JsonException
     * @throws ReflectionException
     */
    public function __construct(
        #[StringNotEmpty] public readonly string $message,
        public readonly Type $type
    ) {
        parent::__construct();
    }

    /**
     * Retrieved escaped message for rendering.
     */
    public function getMessage(): string
    {
        return $this->message;
    }
}
