<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Session;

use Resursbank\Ecom\Exception\SessionException;
use Resursbank\Ecom\Lib\Session\Session as BaseSession;
use Resursbank\Ecom\Lib\Session\SessionHandlerInterface;

/**
 * Session handler integration to use the WooCommerce session handler, which
 * stores session data in the database instead of files.
 */
class Session extends BaseSession implements SessionHandlerInterface
{
    /**
     * @inheritDoc
     */
    public function set(string $key, ?string $val): void
    {
        WC()->session->set(key: self::getKey(key: $key), value: $val);
    }

    /**
     * @inerhitDoc
     */
    public function get(string $key): string
    {
        return (string) WC()->session->get(key: self::getKey(key: $key));
    }

    /**
     * @inheritDoc
     * @throws SessionException
     */
    public function delete(string $key): void
    {
        $this->set(key: self::getKey(key: $key), val: null);
    }
}
