<?php
/**
 * Plugin Name: Tornevalls Resurs Bank payment gateway for WooCommerce
 * Description: Connect Resurs Bank as WooCommerce payment gateway.
 * WC Tested up to: 6.9.1
 * Requires PHP: 7.3
 * Version: 0.0.1.7
 * Author: Tomas Tornevall
 * Plugin URI: https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce
 * Author URI: https://developer.tornevall.net/
 * Text Domain: tornevalls-resurs-bank-payment-gateway-for-woocommerce
 * Domain Path: /language
 *
 * @noinspection PhpCSValidationInspection
 * @noinspection PhpDefineCanBeReplacedWithConstInspection
 */

use Resursbank\Ecom\Config;
use ResursBank\Module\Data;
use ResursBank\ResursBank\ResursPlugin;
use ResursBank\Service\WooCommerce;
use ResursBank\Service\WordPress;
use Resursbank\Woocommerce\Settings\Advanced;
use Resursbank\Woocommerce\Settings\Api;

if (!defined(constant_name: 'ABSPATH')) {
    exit;
}

// Using same path identifier as the rest of the plugin-verse.
define(constant_name: 'RESURSBANK_GATEWAY_PATH', value: plugin_dir_path(__FILE__));

require_once(__DIR__ . '/vendor/autoload.php');

// Note: The prefix below is used by this plugin only and should not be changed. Instead
// you should use the filter "rbwc_get_plugin_prefix", if you really need to change this.
// @todo Build us out of this prefix.
if (Data::isOriginalCodeBase()) {
    define(constant_name: 'RESURSBANK_PREFIX', value: 'trbwc');
} elseif (ResursPlugin::isResursCodeBase()) {
    // Look for an alternative origin.
    define(constant_name: 'RESURSBANK_PREFIX', value: ResursPlugin::RESURS_BANK_PREFIX);
}

// Do not touch this just yet. Converting filters to something else than snake_cases has to be done
// in one sweep - if necessary.
define(constant_name: 'RESURSBANK_SNAKE_CASE_FILTERS', value: true);

//@todo According to WOO-817, this feature may be misconfigured.
define(constant_name: 'RESURSBANK_ALLOW_PAYMENT_FEE',
    value: WordPress::applyFilters(filterName: 'allowPaymentFee', value: false)
);

// Early initiation.
Config::setup(
    logger: Advanced::getLogger(),
    cache: Advanced::getCache(),
    jwtAuth: Api::getJwt()
);

// @todo In a near future, this text domain should be changed to the proper slug or removed.
// @todo If *all* translations moves to ecom2 translator class, it can be entirely removed.
// @todo This avoids a lot of slug problems.
load_plugin_textdomain(
    domain: 'tornevalls-resurs-bank-payment-gateway-for-woocommerce',
    plugin_rel_path: dirname(plugin_basename(__FILE__)) . '/language/'
);

// Make sure there is an instance of WooCommerce among active plugins. If not, we don't
// have to run any of our code, since we're depending on WooCommerce.
if (!WooCommerce::getActiveState()) {
    return;
}

// This is the part where we usually initialized the plugin by a "plugins loaded"-hook,
// or checking that we're in "wordpress mode" with if (function_exists('add_action')) {}.
add_action('plugins_loaded', 'ResursBank\Service\WordPress::initializeWooCommerce');
// Necessary on an early level.
add_filter('rbwc_get_custom_form_fields', 'ResursBank\Module\FormFields::getDeveloperTweaks', 10, 2);
