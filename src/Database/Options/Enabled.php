<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options;

use Resursbank\Woocommerce\Database\StringOption;

/**
 * Setting for globally enabling the gateway (not the plugin).
 */
class Enabled extends StringOption
{
	/**
	 * @inheritdoc
	 */
	public static function getName(): string
    {
        return self::NAME_PREFIX . 'enabled';
    }

    /**
     * @return bool
     */
    public static function isEnabled(): bool
    {
        return self::getData() === 'yes';
    }
}
