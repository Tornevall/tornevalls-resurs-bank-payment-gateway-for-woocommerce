<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

/**
 * URL related helper methods.
 */
class Url
{
	public const NAMESPACE = 'resursbank';

	/**
	 * Helper to get script file from sub-module resource directory.
	 *
	 * @param string $module
	 * @param string $file | File path relative to resources dir.
	 * @return string
	 */
	public static function getScriptUrl(
		string $module,
		string $file
	): string {
		// NOTE: plugin_dir_url returns everything up to the last slash.
		return plugin_dir_url(
			file: RESURSBANK_MODULE_DIR_NAME . "/src/Modules/$module/resources/js/$file"
		) . $file;
	}

//	/**
//	 * @param string $segment
//	 * @return string
//	 */
//	public static function getControllerUri(
//		string $segment
//	): string {
//		return "/resursbank/${segment}";
//	}

//	public function getControllerUrl(
//		string $uri
//	): string {
////		return get_rest_url()
//	}
}
