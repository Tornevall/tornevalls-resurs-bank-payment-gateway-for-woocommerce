=== Resurs Bank Payments for WooCommerce ===
Contributors: Tornevall
Tags: WooCommerce, Resurs Bank, Payment, Payment gateway, ResursBank, payments, checkout, hosted, simplified, hosted flow, simplified flow
Requires at least: 5.5
Tested up to: 6.0
Requires PHP: 7.4
Stable tag: 0.0.1.8
Plugin URI: https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Resurs Bank Payment Gateway for WooCommerce.

== Description ==

Payment gateway for Resurs Bank in WooCommerce.

*Please read the warning notes about testing and installing this plugin directly in a production environment below.*

= Supported shop flows =

* [Merchant REST API](https://test.resurs.com/docs/display/ecom/Merchant+API). This is the new "simplified shop flow", rest based and entirely developed **without** SOAP/XML.

The README you're reading right now is considered belonging to a brand new version, that can also potentially break something if
you tend to handle it as an upgrade from the older plugin (that currently is at v2.2). Running them side by side can also break things badly.

== WARNING ==

**First time running should be a dedicated test environment!**

The main responsibility that this product works properly with your system is yours. For **your** safety you should therefore **TEST the plugin** in a dedicated test environment **before using it** in a production.

If you are entirely new to this plugin or WordPress overall, I'd suggest you to run it in a dedicated test environment that is **equal** to your production environment. Never run any tests in production!

Primary new problems should be discovered in TEST rather than production since the costs are way lower, where no real people are depending on failed orders or payments. If something fails in production it also means that you are the one that potentially looses traffic while your site is down.

= System prerequisites =

Take a look at [Code of Conduct and Migrations](https://docs.tornevall.net/display/TORNEVALL/Code+of+Conduct+and+Migrations#CodeofConductandMigrations-PHPVersions) for an extended explanation about how PHP is used.

* **Required**: PHP: 8.1 or later.
* **Required**: WooCommerce: v3.5.0 or higher - preferably *always* the latest release!
* **Required**: SSL - HTTPS **must** be **fully** enabled. This is a callback security measure, which is required from Resurs Bank.
* **Required**: CURL (php-curl).
* WordPress: Preferably simply the latest release. It is highly recommended to go for the latest version as soon as possible if you're not already there. See [here](https://make.wordpress.org/core/handbook/references/php-compatibility-and-wordpress-versions/) for more information.

= Contribute =

== Installation ==

Preferred Method is to install and activate the plugin through the WordPress plugin installer.

1. Upload the plugin archive to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Configure the plugin via Resurs Bank control panel in admin.

== Frequently Asked Questions ==

= Where can I get more information about this plugin? =

In the documentation of Resurs.

= Can I upgrade from version 2.2.x? =

No.

== Screenshots ==

== Changelog ==

== Upgrade Notice ==
