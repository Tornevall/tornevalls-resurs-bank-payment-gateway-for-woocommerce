<?php
/**
 * Plugin Name: Tornevalls Resurs Bank payment gateway for WooCommerce
 * Description: Connect Resurs Bank as WooCommerce payment gateway.
 * WC Tested up to: 6.6.0
 * Requires PHP: 7.3
 * Version: 0.0.1.7
 * Author: Tomas Tornevall
 * Plugin URI: https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce
 * Author URI: https://developer.tornevall.net/
 * Text Domain: tornevalls-resurs-bank-payment-gateway-for-woocommerce
 * Domain Path: /language
 *
 * @noinspection PhpCSValidationInspection
 */

use ResursBank\Module\Data;
use ResursBank\ResursBank\ResursPlugin;
use ResursBank\Service\WooCommerce;
use ResursBank\Service\WordPress;

if (!defined('ABSPATH')) {
    exit;
}

require_once(__DIR__ . '/vendor/autoload.php');
require_once(__DIR__ . '/globals.php');

// Note: The prefix below is used by this plugin only and should not be changed. Instead
// you should use the filter "rbwc_get_plugin_prefix", if you really need to change this.
define('RESURSBANK_GATEWAY_PATH', plugin_dir_path(__FILE__));
if (Data::isOriginalCodeBase()) {
    define('RESURSBANK_PREFIX', 'trbwc');
} elseif (ResursPlugin::isResursCodeBase()) {
    // Look for an alternative origin.
    define('RESURSBANK_PREFIX', ResursPlugin::RESURS_BANK_PREFIX);
}
define('RESURSBANK_SNAKECASE_FILTERS', true);
define('RESURSBANK_ALLOW_PAYMENT_FEE', WordPress::applyFilters('allowPaymentFee', false));

load_plugin_textdomain(
    'tornevalls-resurs-bank-payment-gateway-for-woocommerce',
    false,
    dirname(plugin_basename(__FILE__)) . '/language/'
);
if (!WooCommerce::getActiveState()) {
    return;
}
// Check and generate admin message if necessary.
Data::getExpectations();

// This is the part where we usually initialized the plugin by a "plugins loaded"-hook,
// or checking that we're in "wordpress mode" with if (function_exists('add_action')) {}.
add_action('plugins_loaded', 'ResursBank\Service\WordPress::initializeWooCommerce');
// Necessary on an early level.
add_filter('rbwc_get_custom_form_fields', 'ResursBank\Module\FormFields::getDeveloperTweaks', 10, 2);
