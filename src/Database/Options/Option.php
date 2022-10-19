<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options;

use RuntimeException;

/**
 * Basic database interface for options in wp_options table.
 */
class Option implements OptionInterface
{
	/**
	 * @inheritdoc
	 */
	public static function getName(): string
	{
		throw new RuntimeException('Not implemented');
	}

	/**
	 * @inheritdoc
	 */
	public static function getData(): ?string
	{
		return get_option(option: self::getName(), default: null);
	}

	/**
	 * @inheritdoc
	 */
	public static function setData(string $value): bool
	{
		self::validate(value: $value);
		return update_option(option: self::getName(), value: $value);
	}

	/**
	 * @inheritdoc
	 */
	public static function validate(string $value): void
	{
	}
}
