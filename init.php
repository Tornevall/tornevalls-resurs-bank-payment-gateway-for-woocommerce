<?php
/**
 * Plugin Name: Tornevall Networks Resurs Bank payment gateway for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/tornevall-networks-resurs-bank-payment-gateway-for-woocommerce
 * Description: Connect Resurs Bank as WooCommerce payment gateway.
 * WC Tested up to: 4.2.2
 * Version: 0.0.1.0
 * Author: Tomas Tornevall
 * Author URI:
 * Text Domain: trbwc
 * Domain Path: /languages
 */

use ResursBank\WordPress;

if (!defined('ABSPATH')) {
    exit;
}
define('RESURSBANK_GATEWAY_PATH', plugin_dir_path(__FILE__));
define('RESURSBANK_IS_DEVELOPER', true);
define('RESURSBANK_PREFIX', 'trbwc');

require_once(__DIR__ . '/vendor/autoload.php');

if (function_exists('add_action')) {
    WordPress::initializePage();
}
