=== Resurs Bank Payments for WooCommerce ===
Contributors: RB-Tornevall
Tags: WooCommerce, Resurs Bank, Payment, Payment gateway, ResursBank, payments, checkout, hosted, simplified, hosted flow, simplified flow
Requires at least: 6.0
Tested up to: 6.7.2
Requires PHP: 8.1
WC Tested up to: 9.6.2
WC requires at least: 7.6.0
Plugin requires ecom: 3.1.6
Requires Plugins: woocommerce
Stable tag: 1.2.1
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

# 1.2.0 - 1.2.1

[WOO-1418](https://resursbankplugins.atlassian.net/browse/WOO-1418) Implement breaking changes for Part Payment widget
[WOO-1420](https://resursbankplugins.atlassian.net/browse/WOO-1420) Implementation of New Part Payment Widget and Warning Widget in Checkout – Compliance with New Legal Requirements
[WOO-1423](https://resursbankplugins.atlassian.net/browse/WOO-1423) \\Resursbank\\Woocommerce\\Modules\\PartPayment\\PartPayment::getWidget
[WOO-1424](https://resursbankplugins.atlassian.net/browse/WOO-1424) Restructure css/js for legal requirements to not execute under partpayment scripthooks
[WOO-1425](https://resursbankplugins.atlassian.net/browse/WOO-1425) \\Resursbank\\Woocommerce\\Modules\\PartPayment\\PartPayment::setCss

# 1.1.5

* [WOO-1417](https://resursbankplugins.atlassian.net/browse/WOO-1417) ppw period resets to wrong value

# 1.1.1 - 1.1.4

* [WOO-1411](https://resursbankplugins.atlassian.net/browse/WOO-1411) About-widget broken
* [WOO-1413](https://resursbankplugins.atlassian.net/browse/WOO-1413) Some stores, during upgrade, may get JWT errors
* [WOO-1415](https://resursbankplugins.atlassian.net/browse/WOO-1415) Remove \(if possible\) extra sort order on blocks methods
* [WOO-1416](https://resursbankplugins.atlassian.net/browse/WOO-1416) slow loading with get-address?
* [WOO-1414](https://resursbankplugins.atlassian.net/browse/WOO-1414) isEnabled shouts false positives
* [WOO-1413](https://resursbankplugins.atlassian.net/browse/WOO-1413) Some stores, during upgrade, may get JWT errors
* Uncatched blocks exception handled.

# 1.1.1

* [WOO-1413](https://resursbankplugins.atlassian.net/browse/WOO-1413) Some stores, during upgrade, may get JWT errors

# 1.1.0

* [WOO-1373](https://resursbankplugins.atlassian.net/browse/WOO-1373) Update src/Modules/GetAddress/resources/update-address/legacy.js
* [WOO-1379](https://resursbankplugins.atlassian.net/browse/WOO-1379) Confirm functionality of logged in customer
* [WOO-1384](https://resursbankplugins.atlassian.net/browse/WOO-1384) wp-admin payment method editor says incompatible methods
* [WOO-1403](https://resursbankplugins.atlassian.net/browse/WOO-1403) New url to docs in readme
* [WOO-1407](https://resursbankplugins.atlassian.net/browse/WOO-1407) Investigation of Support for Payment Method Management and Sorting in WooCommerce Blocks
* [WOO-1396](https://resursbankplugins.atlassian.net/browse/WOO-1396) Missing company payment method
* [WOO-1397](https://resursbankplugins.atlassian.net/browse/WOO-1397) Legacy checkout do not reload payment methods
* [WOO-1400](https://resursbankplugins.atlassian.net/browse/WOO-1400) Purchase button invalidates in specific occasions for some LEGAL method
* [WOO-1402](https://resursbankplugins.atlassian.net/browse/WOO-1402) Billing address are not seind in deliveryAddress with blocks
* [WOO-1404](https://resursbankplugins.atlassian.net/browse/WOO-1404) Error message from Merchant-api is missing
* [WOO-1405](https://resursbankplugins.atlassian.net/browse/WOO-1405) For a Finnish account, the threshold value should be 15€
* [WOO-1406](https://resursbankplugins.atlassian.net/browse/WOO-1406) The Legacy checkout does not list the "correct" payment methods at checkout depending on the country
* [WOO-1409](https://resursbankplugins.atlassian.net/browse/WOO-1409) Email is not properly added to payload when order are created
* [WOO-1410](https://resursbankplugins.atlassian.net/browse/WOO-1410) Send the personal identification number, email, and mobile number of the person responsible for the payment, i.e., the details required by the service provider.
* [WOO-1412](https://resursbankplugins.atlassian.net/browse/WOO-1412) PPW not showing after resetting values
* [WOO-1378](https://resursbankplugins.atlassian.net/browse/WOO-1378) Test Blocks and Legacy


== Upgrade Notice ==
