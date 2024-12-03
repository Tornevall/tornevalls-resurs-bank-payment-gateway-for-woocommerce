=== Resurs Bank Payments for WooCommerce ===
Contributors: RB-Tornevall
Tags: WooCommerce, Resurs Bank, Payment, Payment gateway, ResursBank, payments, checkout, hosted, simplified, hosted flow, simplified flow
Requires at least: 6.0
Tested up to: 6.6.2
Requires PHP: 8.1
WC Tested up to: 9.2.3
WC requires at least: 7.6.0
Plugin requires ecom: 3.1.1
Requires Plugins: woocommerce
Stable tag: 1.0.56
Plugin URI: https://developers.resurs.com/platform-plugins/woocommerce/resurs-merchant-api-2.0-for-woocommerce/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Resurs Bank Payment Gateway for WooCommerce.

== Description ==

**Please note that there is a known problem with WooCommerce blocks for the checkout for which payment methods are not properly displayed. This can be fixed by removing the content in the checkout-page blocks and add the shortcode [woocommerce_checkout] instead.**

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

# 1.0.56

* [WOO-1246](https://resursbankplugins.atlassian.net/browse/WOO-1246) Resolve default store id by using ECom method instead
* [WOO-1359](https://resursbankplugins.atlassian.net/browse/WOO-1359) Remove getSiteLanguage and \\Resursbank\\Woocommerce\\Database\\Options\\Api\\StoreCountryCode
* [WOO-1366](https://resursbankplugins.atlassian.net/browse/WOO-1366) 1359/1246: Clean up Language/Country/Default Store transient handling.
* [WOO-1364](https://resursbankplugins.atlassian.net/browse/WOO-1364) Error in ecom.log when fetchAddress in checkout
* [WOO-1381](https://resursbankplugins.atlassian.net/browse/WOO-1381) Missing type check in Modules\\Gateway\\Gateway::modifyIcon

# 1.0.50 - 1.0.55

* Miscellaneous hotfixes.
* [WOO-1355](https://resursbankplugins.atlassian.net/browse/WOO-1355) PPW renders duplicate payment \(and too many\) payment methods
* [WOO-1353](https://resursbankplugins.atlassian.net/browse/WOO-1353) Memory exhaustion patch.

# 1.0.50

* [WOO-1353](https://resursbankplugins.atlassian.net/browse/WOO-1353) Memory exhaustion patch.

# 1.0.49

* [WOO-1343](https://resursbankplugins.atlassian.net/browse/WOO-1343) Adjust for ECP-855 changes
* [WOO-1345](https://resursbankplugins.atlassian.net/browse/WOO-1345) Apply ECP-860 changes
* [WOO-1351](https://resursbankplugins.atlassian.net/browse/WOO-1351) Unable to handle payments not belonging to Resurs in special circumstances

# 1.0.43 - 1.0.48

* Hotfixes for various problems. One covers a "live" vs "coming soon"-pages issue. Another one is based on a misplaced script-tag.

# 1.0.42

* [WOO-1308](https://resursbankplugins.atlassian.net/browse/WOO-1308) Upgrade to v3
* [WOO-1332](https://resursbankplugins.atlassian.net/browse/WOO-1332) getAddress don't show up
* [WOO-1333](https://resursbankplugins.atlassian.net/browse/WOO-1333) get use of rb-ga-gov-id to fill in company name on LEGAL
* [WOO-1337](https://resursbankplugins.atlassian.net/browse/WOO-1337) Raise woocommerce too ecom v3-tag
* [WOO-1329](https://resursbankplugins.atlassian.net/browse/WOO-1329) Fix annuity-factor widget in admin \(Breaking change for v3\).
* [WOO-1334](https://resursbankplugins.atlassian.net/browse/WOO-1334) createPaymentRequest classes moved to models
* [WOO-1335](https://resursbankplugins.atlassian.net/browse/WOO-1335) PPW: Period laddas inte vid val av betalmetod
* [WOO-1336](https://resursbankplugins.atlassian.net/browse/WOO-1336) Admin: Butiksval laddas inte in vi byte av credentials
* [WOO-1338](https://resursbankplugins.atlassian.net/browse/WOO-1338) Government id not submitted to createpayment
* [WOO-1339](https://resursbankplugins.atlassian.net/browse/WOO-1339) Modify seems to not work properly
* [WOO-1340](https://resursbankplugins.atlassian.net/browse/WOO-1340) Bulk finalization may still cause booleans instead of null when using getOrder
* [WOO-1342](https://resursbankplugins.atlassian.net/browse/WOO-1342) About-widget errors


== Upgrade Notice ==

