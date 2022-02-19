<?php
/**
 * @noinspection PhpCSValidationInspection
 * Plugin Name: Tornevalls Resurs Bank payment gateway for WooCommerce
 * Description: Connect Resurs Bank as WooCommerce payment gateway.
 * WC Tested up to: 6.2.0
 * Requires PHP: 7.0
 * Version: 0.0.1.4
 * Author: Tomas Tornevall
 * Plugin URI: https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce
 * Author URI: https://developer.tornevall.net/
 * Text Domain: tornevalls-resurs-bank-payment-gateway-for-woocommerce
 * Domain Path: /language
 */

use ResursBank\Module\Data;
use ResursBank\Service\WooCommerce;

define('RESURSBANK_GATEWAY_PATH', plugin_dir_path(__FILE__));
define('RESURSBANK_PREFIX', 'trbwc');
define('RESURSBANK_SNAKECASE_FILTERS', true);

require_once(__DIR__ . '/vendor/autoload.php');

if (!defined('ABSPATH')) {
    exit;
}
if (!WooCommerce::getActiveState()) {
    return;
}
// Check and generate admin message if necessary.
Data::getExpectations();

load_plugin_textdomain(
    'tornevalls-resurs-bank-payment-gateway-for-woocommerce',
    false,
    dirname(plugin_basename(__FILE__)) . '/language/'
);

// This is the part where we usually initialized the plugin by a "plugins loaded"-hook,
// or checking that we're in "wordpress mode" with if (function_exists('add_action')) {}.
add_action('plugins_loaded', 'ResursBank\Service\WordPress::initializePlugin');
// Necessary on an early level.
add_filter('rbwc_get_custom_form_fields', 'ResursBank\Module\FormFields::getDeveloperTweaks', 10, 2);
add_filter('rbwc_get_custom_form_fields', 'ResursBank\Module\FormFields::getBleedingEdgeSettings', 10, 2);
// Making sure that we do not coexist with prior versions.
add_filter('resurs_obsolete_coexistence_disable', 'ResursBank\Service\WordPress::getPriorVersionsDisabled');
