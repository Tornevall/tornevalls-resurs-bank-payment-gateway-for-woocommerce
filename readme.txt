=== Resurs Bank Payments for WooCommerce ===
Contributors: RB-Tornevall
Tags: WooCommerce, Resurs Bank, Payment, Payment gateway, ResursBank, payments, checkout, hosted, simplified, hosted flow, simplified flow
Requires at least: 6.0
Tested up to: 6.7.2
Requires PHP: 8.1
WC Tested up to: 9.6.2
WC requires at least: 7.6.0
Plugin requires ecom: master
Requires Plugins: woocommerce
Stable tag: 1.2.3
Plugin URI: https://developers.resurs.com/platform-plugins/woocommerce/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Resurs Bank Payment Gateway for WooCommerce.

== Description ==

A payment is expected to be simple, secure and fast, regardless of whether it takes place in a physical store or online. With over 6 million customers around the Nordics, we make sure to be up-to-date with smart payment solutions where customers shop.

At checkout, your customer can choose between several flexible payment options, something that not only provides a better shopping experience but also generates more and larger purchases.

[Sign up for Resurs](https://www.resursbank.se/betallosningar)!
Find out more in about the plugin [in our documentation](https://developers.resurs.com/platform-plugins/woocommerce/).

= System Requirements =

* **Required**: PHP 8.1 or higher.
* **Required**: WooCommerce: At least v7.6.0
* **Required**: SSL - HTTPS **must** be **fully** enabled. This is a callback security measure, which is required from Resurs Bank.
* **Required**: CURL (php-curl) with **CURLAUTH_BEARER**.
* Preferably the **latest** release of WordPress. See [here](https://make.wordpress.org/core/handbook/references/php-compatibility-and-wordpress-versions/) for more information.


== Installation ==

Preferred Method is to install and activate the plugin through the WordPress plugin installer.

Doing it manually? Look below.

1. Upload the plugin archive to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Configure the plugin via Resurs Bank control panel in admin.

== Frequently Asked Questions ==

= Where can I get more information about this plugin? =

Find out more about the plugin [in our documentation](https://developers.resurs.com/platform-plugins/woocommerce/).

= Can I upgrade from version 2.2.x? =

No (this is a breaking change). But if you've used the old version before, historical payments are transparent and can be handled by this new release.
If you wish to upgrade from the old plugin release, you need to contact Resurs Bank for new credentials.

== Screenshots ==

== Changelog ==

[See full changelog here](https://bitbucket.org/resursbankplugins/resursbank-woocommerce/src/master/CHANGELOG.md).

# 1.2.3

* [WOO-1428](https://resursbankplugins.atlassian.net/browse/WOO-1428) Instead of doing all country checks in the plugin, we should take advantage of Location that was unreachable from Config::setup before
* [WOO-1422](https://resursbankplugins.atlassian.net/browse/WOO-1422) Performance issues and bugs for costlist
* [WOO-1429](https://resursbankplugins.atlassian.net/browse/WOO-1429) Changing stores does not necessarily mean we're clearing the entire cache
* [WOO-1431](https://resursbankplugins.atlassian.net/browse/WOO-1431) Running Resurs plugin with WooCommerce disabled.
* [WOO-1432](https://resursbankplugins.atlassian.net/browse/WOO-1432) Update ecom to show necessary values in cost-list

# 1.2.2

* [WOO-1426](https://resursbankplugins.atlassian.net/browse/WOO-1426) Unsupported themes makes widgets go nuts \(sometimes\)
* [WOO-1427](https://resursbankplugins.atlassian.net/browse/WOO-1427) Country checks required in react

# 1.2.0 - 1.2.1

* [WOO-1418](https://resursbankplugins.atlassian.net/browse/WOO-1418) Implement breaking changes for Part Payment widget
* [WOO-1420](https://resursbankplugins.atlassian.net/browse/WOO-1420) Implementation of New Part Payment Widget and Warning Widget in Checkout – Compliance with New Legal Requirements
* [WOO-1423](https://resursbankplugins.atlassian.net/browse/WOO-1423) \\Resursbank\\Woocommerce\\Modules\\PartPayment\\PartPayment::getWidget
* [WOO-1424](https://resursbankplugins.atlassian.net/browse/WOO-1424) Restructure css/js for legal requirements to not execute under partpayment scripthooks
* [WOO-1425](https://resursbankplugins.atlassian.net/browse/WOO-1425) \\Resursbank\\Woocommerce\\Modules\\PartPayment\\PartPayment::setCss

# 1.2.0 - 1.2.1

[WOO-1418](https://resursbankplugins.atlassian.net/browse/WOO-1418) Implement breaking changes for Part Payment widget
[WOO-1420](https://resursbankplugins.atlassian.net/browse/WOO-1420) Implementation of New Part Payment Widget and Warning Widget in Checkout – Compliance with New Legal Requirements
[WOO-1423](https://resursbankplugins.atlassian.net/browse/WOO-1423) \\Resursbank\\Woocommerce\\Modules\\PartPayment\\PartPayment::getWidget
[WOO-1424](https://resursbankplugins.atlassian.net/browse/WOO-1424) Restructure css/js for legal requirements to not execute under partpayment scripthooks
[WOO-1425](https://resursbankplugins.atlassian.net/browse/WOO-1425) \\Resursbank\\Woocommerce\\Modules\\PartPayment\\PartPayment::setCss

# 1.1.5

* [WOO-1417](https://resursbankplugins.atlassian.net/browse/WOO-1417) ppw period resets to wrong value

== Upgrade Notice ==

Avoid running auto upgrade functions in the platform.
