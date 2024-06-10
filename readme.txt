=== Resurs Bank Payments for WooCommerce ===
Contributors: RB-Tornevall
Tags: WooCommerce, Resurs Bank, Payment, Payment gateway, ResursBank, payments, checkout, hosted, simplified, hosted flow, simplified flow
Requires at least: 6.0
Tested up to: 6.4.3
Requires PHP: 8.1
WC Tested up to: 8.8.2
WC requires at least: 7.6.0
Plugin requires ecom: 2.0.5
Stable tag: 1.0.32
Plugin URI: https://developers.resurs.com/platform-plugins/woocommerce/resurs-merchant-api-2.0-for-woocommerce/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Resurs Bank Payment Gateway for WooCommerce.

== Description ==

A payment is expected to be simple, secure and fast, regardless of whether it takes place in a physical store or online. With over 6 million customers around the Nordics, we make sure to be up-to-date with smart payment solutions where customers shop.

At checkout, your customer can choose between several flexible payment options, something that not only provides a better shopping experience but also generates more and larger purchases.

[Sign up for Resurs](https://www.resursbank.se/betallosningar)!
Find out more in about the plugin [in our documentation](https://developers.resurs.com/platform-plugins/woocommerce/resurs-merchant-api-2.0-for-woocommerce/).

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

Find out more about the plugin [in our documentation](https://developers.resurs.com/platform-plugins/woocommerce/resurs-merchant-api-2.0-for-woocommerce/).

= Can I upgrade from version 2.2.x? =

No (this is a breaking change). But if you've used the old version before, historical payments are transparent and can be handled by this new release.
If you wish to upgrade from the old plugin release, you need to contact Resurs Bank for new credentials.

== Screenshots ==

== Changelog ==

[See full changelog here](https://bitbucket.org/resursbankplugins/resursbank-woocommerce/src/master/CHANGELOG.md).

# 1.0.32

[WOO-1310](https://resursbankplugins.atlassian.net/browse/WOO-1310) Frozen\+Rejected orders are cancelled twice

# 1.0.31

* [WOO-1306](https://resursbankplugins.atlassian.net/browse/WOO-1306) Payment methods by country limit

# 1.0.29 - 1.0.30

* Issues eventually discovered with composer.json

# 1.0.28

* Translation hotfix.

# 1.0.27 (ECom2-2.x)

* [WOO-1291](https://resursbankplugins.atlassian.net/browse/WOO-1291) Add plugin and platform information to the metadata
* [WOO-1293](https://resursbankplugins.atlassian.net/browse/WOO-1293) Verification of woo with ECP-636
* [WOO-1295](https://resursbankplugins.atlassian.net/browse/WOO-1295) Complete credit-denied
* [WOO-1299](https://resursbankplugins.atlassian.net/browse/WOO-1299) Add an X-close-modal at payment method level in checkout area
* [WOO-1302](https://resursbankplugins.atlassian.net/browse/WOO-1302) Implement revised changes from Payment Information module \(ecom2-v2\)
* [WOO-1305](https://resursbankplugins.atlassian.net/browse/WOO-1305) Release of single patch from  ECP-745 \(finnish translation for partpay string\)
* [WOO-1294](https://resursbankplugins.atlassian.net/browse/WOO-1294) Ecom2 v2.0 locale/language changes
* [WOO-1297](https://resursbankplugins.atlassian.net/browse/WOO-1297) Testing callbacks fails
* [WOO-1298](https://resursbankplugins.atlassian.net/browse/WOO-1298) Layout issues in cost info
* [WOO-1300](https://resursbankplugins.atlassian.net/browse/WOO-1300) Annuity after reload issues
* [WOO-1304](https://resursbankplugins.atlassian.net/browse/WOO-1304) Read more/Priceinfovy i kassa: Unable to fetch product, #0 \[...\]PartPayment.php\(83\)

# 1.0.24 - 1.0.26

* [WOO-1305](https://resursbankplugins.atlassian.net/browse/WOO-1305) Finnish translation for part payment info.

# 1.0.23

* Only tag change.

# 1.0.23

* Only tag change.

# 1.0.22

* [WOO-1292](https://resursbankplugins.atlassian.net/browse/WOO-1292) Norska basöversättningar för woocommerce

# 1.0.21

* [WOO-1290](https://resursbankplugins.atlassian.net/browse/WOO-1290) Mobile view for priceinfo may be broken depending on the width

# 1.0.20

* [WOO-1289](https://resursbankplugins.atlassian.net/browse/WOO-1289) Payment information widget should have z-index 999999 \(linked issue for deploy in Woocommerce\)

# 1.0.19

* [WOO-1288](https://resursbankplugins.atlassian.net/browse/WOO-1288) Partpayment infotext \(norwegian\)

# 1.0.18

* [WOO-1285](https://resursbankplugins.atlassian.net/browse/WOO-1285) Typed static property Resursbank\\Ecom\\Config::$instance must not be accessed before initialization
* [WOO-1283](https://resursbankplugins.atlassian.net/browse/WOO-1283) locale_rework

# 1.0.17

* [WOO-1283](https://resursbankplugins.atlassian.net/browse/WOO-1283) Findings: fi/no translations for checkout

# 1.0.16

* [WOO-1282](https://resursbankplugins.atlassian.net/browse/WOO-1282) Disabla getAddress-wiget om vald butik <> "countryCode": "SE"

# 1.0.15

* [WOO-1279](https://resursbankplugins.atlassian.net/browse/WOO-1279) Verify the plugin with rcoplus branch and WooCommerce 8.5.0
* [WOO-1280](https://resursbankplugins.atlassian.net/browse/WOO-1280) Undefined array key warnings in checkout/wp-admin

# 1.0.14

* Rebuilt commit.

# 1.0.13

* [WOO-1274](https://resursbankplugins.atlassian.net/browse/WOO-1274) Handle gateway sort order in checkout based on wp-admin setup
* [WOO-1267](https://resursbankplugins.atlassian.net/browse/WOO-1267) Make "tested up to 8.0.x" work properly.
* [WOO-1276](https://resursbankplugins.atlassian.net/browse/WOO-1276) Read more CSS malfunction on custom themes

# 1.0.12

* [WOO-1275](https://resursbankplugins.atlassian.net/browse/WOO-1275) Variable products part payment script errors

# 1.0.11

* [WOO-1271](https://resursbankplugins.atlassian.net/browse/WOO-1271) /price\_signage?amount=0" tiggas 1000 ggr per dag
* [WOO-1272](https://resursbankplugins.atlassian.net/browse/WOO-1272) Regler för paymentwidget
* [WOO-1273](https://resursbankplugins.atlassian.net/browse/WOO-1273) Switching between customer types may confuse the platform customertype

# 1.0.10

* [WOO-1270](https://resursbankplugins.atlassian.net/browse/WOO-1270) Enforce input field for companies \(company id\)

# 1.0.9

* [WOO-1264](https://resursbankplugins.atlassian.net/browse/WOO-1264) Change behaviour of how payment gateways/methods are displayed in admin and checkout pages
* [WOO-1265](https://resursbankplugins.atlassian.net/browse/WOO-1265) Redirect from "payments" no longer works
* [WOO-1261](https://resursbankplugins.atlassian.net/browse/WOO-1261) get\_query\_var: Call to a member function get\(\) on null
* [WOO-1266](https://resursbankplugins.atlassian.net/browse/WOO-1266) Prevent min/max, etc to not show warnings when gateway methods is not present
* [WOO-1268](https://resursbankplugins.atlassian.net/browse/WOO-1268) Partpayment admin widget conflicts \(0059993\)

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

