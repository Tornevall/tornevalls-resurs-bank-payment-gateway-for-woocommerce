<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options;

use Resursbank\Woocommerce\Database\StringOption;

/**
 * Database interface for cache_dir in wp_options table.
 *
 * @todo Add value validation before appending value to database. Validation should be done inside Ecom. See WOO-800 and ECP-202.
 */
class LogDir extends StringOption
{
	/**
	 * @inheritdoc
	 */
	public static function getName(): string
	{
		return self::NAME_PREFIX . 'log_dir';
	}
}
