<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Database\Options;

use Resursbank\Ecom\Exception\ValidationException;

/**
 * Interface describing methods required to interact with wp_options table.
 */
interface OptionInterface
{
	/**
	 * Name prefix for entries in wp_options table.
	 */
	public const NAME_PREFIX = 'resursbank_';

	/**
	 * Resolve name of entry in wp_options table. This method needs to be
	 * overwritten by extending classes.
	 *
	 * NOTE: Using a method instead of a property to ensure that the name is
	 * not left empty.
	 *
	 * @return string
	 */
	public static function getName(): string;

	/**
	 * @return string|null
	 */
	public static function getData(): ?string;

	/**
	 * @param string $value
	 * @return bool
	 */
	public static function setData(string $value): bool;

	/**
	 * Validate a given value.
	 *
	 * @param string $value
	 * @return void
	 */
	public static function validate(string $value): void;
}
