=== Resurs Bank Payments for WooCommerce ===
Contributors: RB-Tornevall
Tags: WooCommerce, Resurs Bank, Payment, Payment gateway, ResursBank
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.1
WC requires at least: 7.6.0
WC Tested up to: 10.1.1
Plugin requires ecom: 3.2.15
Requires Plugins: woocommerce
Stable tag: 1.2.14
Plugin URI: https://developers.resurs.com/platform-plugins/woocommerce/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Resurs Bank Payment Gateway for WooCommerce.

== Description ==

A payment is expected to be simple, secure and fast, regardless of whether it takes place in a physical store or online. With over 6 million customers around the Nordics, we make sure to be up-to-date with smart payment solutions where customers shop.

At checkout, your customer can choose between several flexible payment options, something that not only provides a better shopping experience but also generates more and larger purchases.

Sign up for Resurs!
Find out more in about the plugin in our documentation.

= System Requirements =

* **Required**: PHP 8.1 or higher.
* **Required**: WooCommerce: At least v7.6.0
* **Required**: SSL - HTTPS **must** be **fully** enabled. This is a callback security measure, which is required from Resurs Bank.
* **Required**: CURL (php-curl) with **CURLAUTH_BEARER**.
* Preferably the **latest** release of WordPress. See here for more information.


== Installation ==

Preferred Method is to install and activate the plugin through the WordPress plugin installer.

Doing it manually? Look below.

1. Upload the plugin archive to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Configure the plugin via Resurs Bank control panel in admin.

== Frequently Asked Questions ==

= Where can I get more information about this plugin? =

Find out more about the plugin in our documentation.

= Can I upgrade from version 2.2.x? =

No (this is a breaking change). But if you've used the old version before, historical payments are transparent and can be handled by this new release.
If you wish to upgrade from the old plugin release, you need to contact Resurs Bank for new credentials.

== Screenshots ==

== Changelog ==

[See the full changelog here.](https://bitbucket.org/resursbankplugins/resursbank-woocommerce/src/master/CHANGELOG.md)
For full documentation, please refer to our [documentation](https://developers.resurs.com/platform-plugins/woocommerce/resurs-merchant-api-for-woocommerce).

Latest changes:

# 1.2.13

* WOO-1474 Consistent part payment pricing via external systems (Lemonsoft, etc) lowest price + correct VAT on product page

# 1.2.12

* WOO-1452 Period is reset each time config enters get-store-country
* WOO-1461 README/Changelog updates
* WOO-1464 ECP Timeouts Control
* WOO-1469 Plugin requires ecom 3.2.9 issue.
* WOO-1470 GetPeriods error

== Upgrade Notice ==

Avoid running auto upgrade functions in the platform.
