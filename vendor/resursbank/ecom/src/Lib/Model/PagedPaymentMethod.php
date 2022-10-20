<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model;

use Resursbank\Ecom\Lib\Model\PaymentMethod\Pagination;

/**
 * Paged payment methods response.
 */
class PagedPaymentMethod extends Model
{
    /**
     * @param PaymentMethodCollection $content
     * @param Pagination $page
     */
    public function __construct(
        private readonly PaymentMethodCollection $content,
        private readonly Pagination $page,
    ) {
    }
}
