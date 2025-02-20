<?php

/**
 * Plugin Name: Resurs Bank Payments for WooCommerce
 * Description: Connect Resurs Bank as WooCommerce payment gateway.
 * WC Tested up to: 9.6.2
 * WC requires at least: 7.6.0
 * Plugin requires ecom: 3.1.6
 * Requires PHP: 8.1
 * Version: 1.2.2
 * Author: Resurs Bank AB
 * Author URI: https://developers.resurs.com/
 * Plugin URI: https://developers.resurs.com/platform-plugins/woocommerce/resurs-merchant-api-2.0-for-woocommerce/
 * Text Domain: resurs-bank-payments-for-woocommerce
 * Requires Plugins: woocommerce
 *
 * @noinspection PhpCSValidationInspection
 * @noinspection PhpDefineCanBeReplacedWithConstInspection
 */

// Welcome to WordPress. SideEffects can not be handled properly while the init looks like this.
// Consider honoring this in the future another way.
// phpcs:disable PSR1.Files.SideEffects


declare(strict_types=1);

use Automattic\WooCommerce\Utilities\FeaturesUtil;
use Resursbank\Ecom\Config;
use Resursbank\Woocommerce\Modules\Api\Connection;
use Resursbank\Woocommerce\Modules\ModuleInit\Admin as AdminInit;
use Resursbank\Woocommerce\Modules\ModuleInit\Frontend;
use Resursbank\Woocommerce\Modules\ModuleInit\Shared;
use Resursbank\Woocommerce\Util\Admin;
use Resursbank\Woocommerce\Util\WooCommerce;

if (!defined(constant_name: 'ABSPATH')) {
    exit;
}

require_once __DIR__ . '/src/Autoloader/requirements.php';

// Do not remove this! It ensures the plugin does not crash the entire site if ecom2
// has not been checked out properly. This issue typically occurs during a manual
// checkout when ecom2 is missing. We cannot move this into a class, as the autoload
// process will fail if ecom2 is unavailable.
if (!file_exists(filename: __DIR__ . '/lib/ecom/composer.json')) {
    resursBankHasNoEcom();
    return;
}

if (PHP_VERSION_ID < 80100) {
    resursBankHasOldPhp();
    return;
}

// Name of plugin directory; normally the slug name.
define(
    constant_name: 'RESURSBANK_MODULE_DIR_NAME',
    value: substr(
        string: __DIR__,
        offset: strrpos(haystack: __DIR__, needle: '/') + 1
    )
);

// Absolute path to plugin directory; "/var/www/html/wp-content/plugins/<the-slug-name>"
define(
    constant_name: 'RESURSBANK_MODULE_DIR_PATH',
    value: plugin_dir_path(file: __FILE__)
);

define(constant_name: 'RESURSBANK_MODULE_PREFIX', value: 'resursbank');

require_once __DIR__ . '/autoload.php';

// Make sure there is an instance of WooCommerce among active plugins.
if (!WooCommerce::isAvailable()) {
    return;
}

// Early initiation. If this request catches an exception, it is mainly caused by unset credentials.
Connection::setup();

// Cannot continue without ECom library instance configured.
if (!Config::hasInstance()) {
    return;
}

// Setup event listeners and resources when WP has finished loading all modules.
add_action(hook_name: 'plugins_loaded', callback: static function (): void {
    Shared::init();
    /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
    add_action(
        'before_woocommerce_init',
        static function (): void {
            if (
                !class_exists(
                    class: 'Automattic\WooCommerce\Utilities\FeaturesUtil'
                )
            ) {
                return;
            }

            /** @noinspection PhpArgumentWithoutNamedIdentifierInspection */
            FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                __FILE__,
                true
            );

            FeaturesUtil::declare_compatibility(
                'cart_checkout_blocks',
                __FILE__,
                true
            );
        }
    );

    if (Admin::isAdmin()) {
        AdminInit::init();
    } else {
        Frontend::init();
    }
});
