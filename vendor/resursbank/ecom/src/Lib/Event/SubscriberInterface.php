<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Event;

use Resursbank\Ecom\Config;

/**
 * Describes an event subscriber.
 */
interface SubscriberInterface
{
    /**
     * @param array $data
     * @return bool
     */
    public function validate(array $data): bool;

    /**
     * NOTE: The Event dispatcher at Resursbank\Ecom\Lib\Event\Hub will execute
     * the validate method before calling this method.
     *
     * @param Config $config
     * @param array $data
     * @return void
     */
    public function execute(Config $config, array $data): void;
}
