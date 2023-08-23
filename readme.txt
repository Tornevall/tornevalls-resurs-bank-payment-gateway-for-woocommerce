=== Resurs Bank Payments for WooCommerce ===
Contributors: RB-Tornevall
Tags: WooCommerce, Resurs Bank, Payment, Payment gateway, ResursBank, payments, checkout, hosted, simplified, hosted flow, simplified flow
Requires at least: 6.0
Tested up to: 6.2.2
Requires PHP: 8.1
Stable tag: 1.0.9
Plugin URI: https://test.resurs.com/docs/display/ecom/WooCommerce
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Resurs Bank Payment Gateway for WooCommerce.

== Description ==

A payment is expected to be simple, secure and fast, regardless of whether it takes place in a physical store or online. With over 6 million customers around the Nordics, we make sure to be up-to-date with smart payment solutions where customers shop.

At checkout, your customer can choose between several flexible payment options, something that not only provides a better shopping experience but also generates more and larger purchases.

[Sign up for Resurs](https://www.resursbank.se/betallosningar)!
Find out more in about the plugin [in our documentation](https://test.resurs.com/docs/x/IoDhB).

= System Requirements =

* **Required**: PHP: 8.1 or later.
* **Required**: WooCommerce: At least v7.6.0
* **Required**: SSL - HTTPS **must** be **fully** enabled. This is a callback security measure, which is required from Resurs Bank.
* **Required**: CURL (php-curl).
* WordPress: Preferably simply the latest release. It is highly recommended to go for the latest version as soon as possible if you're not already there. See [here](https://make.wordpress.org/core/handbook/references/php-compatibility-and-wordpress-versions/) for more information.


== Installation ==

Preferred Method is to install and activate the plugin through the WordPress plugin installer.

Doing it manually? Look below.

1. Upload the plugin archive to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Configure the plugin via Resurs Bank control panel in admin.

== Frequently Asked Questions ==

= Where can I get more information about this plugin? =

Find out more about the plugin [in our documentation](https://test.resurs.com/docs/x/IoDhB).

= Can I upgrade from version 2.2.x? =

No (this is a breaking change). But if you've used the old version before, historical payments are transparent and can be handled by this new release.
If you wish to upgrade from the old plugin release, you need to contact Resurs Bank for new credentials.

== Screenshots ==

== Changelog ==

[See full changelog here](https://bitbucket.org/resursbankplugins/resursbank-woocommerce/src/master/CHANGELOG.md).

# 1.0.8

* [WOO-1261](https://resursbankplugins.atlassian.net/browse/WOO-1261) get\_query\_var: Call to a member function get\(\) on null

# 1.0.7

* [WOO-1260](https://resursbankplugins.atlassian.net/browse/WOO-1260) Fix inaccurate return type in \\Resursbank\\Woocommerce\\Modules\\Order\\Status::orderStatusFromPaymentStatus

# 1.0.6

* [WOO-1249](https://resursbankplugins.atlassian.net/browse/WOO-1249) Timeouts in the store may block site access completely if misconfigured

# 1.0.5 (ECom Upgrade)

[WOO-1257](https://resursbankplugins.atlassian.net/browse/WOO-1257) USP strings with wrong notices when translations are missing

# 1.0.4 (ECom Upgrade)

* [WOO-1252](https://resursbankplugins.atlassian.net/browse/WOO-1252) Changed description length from 50 till 100 in ecom package
* [WOO-1250](https://resursbankplugins.atlassian.net/browse/WOO-1250) Extend logging on getStores errors / Troubleshooting getStores and TLS \(?\)
* [WOO-1253](https://resursbankplugins.atlassian.net/browse/WOO-1253) Error: Failed to obtain store selection box \(ecom-related\)
* [WOO-1254](https://resursbankplugins.atlassian.net/browse/WOO-1254) Msgbox at Resurs settings
* [WOO-1255](https://resursbankplugins.atlassian.net/browse/WOO-1255) Store fetcher does not work

# 1.0.3

* [WOO-1250](https://resursbankplugins.atlassian.net/browse/WOO-1250) Extend logging on getStores errors

# 1.0.2

* [WOO-1248](https://resursbankplugins.atlassian.net/browse/WOO-1248) Unable to switch to production

# 1.0.0 - 1.0.1

[See here for full list](https://bitbucket.org/resursbankplugins/resursbank-woocommerce/src/master/CHANGELOG.md)

== Upgrade Notice ==

