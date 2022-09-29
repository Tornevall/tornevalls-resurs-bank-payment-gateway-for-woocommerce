<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model\Payment\Order;

use Resursbank\Ecom\Lib\Model\Model;
use Resursbank\Ecom\Module\Payment\Enum\PossibleAction as ActionEnum;

/**
 * Defines a possible action for the order.
 */
class PossibleAction extends Model
{
    /**
     * @param ActionEnum|null $action
     */
    public function __construct(
        public readonly ?ActionEnum $action = null,
    ) {
    }
}
