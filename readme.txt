=== Resurs Bank Payments for WooCommerce ===
Contributors: rbonboarding, RB-Tornevall
Tags: WooCommerce, Resurs Bank, Payment, Payment gateway, ResursBank
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 8.1
WC requires at least: 7.6.0
WC Tested up to: 11.0.1
Plugin requires ecom: 3.4.3
Plugin tested up to: PHP 8.6
Requires Plugins: woocommerce
Stable tag: 1.2.36
Plugin URI: https://developers.resurs.com/platform-plugins/woocommerce/
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Resurs Bank Payment Gateway for WooCommerce.

== Description ==

A payment is expected to be simple, secure and fast, regardless of whether it takes place in a physical store or online. With over 6 million customers around the Nordics, we make sure to be up-to-date with smart payment solutions where customers shop.

At checkout, your customer can choose between several flexible payment options, something that not only provides a better shopping experience but also generates more and larger purchases.

Sign up for Resurs!
[Find out more in about the plugin in our documentation](https://developers.resurs.com/platform-plugins/woocommerce).

## System Requirements

- Required: PHP 8.1 or higher
- Required: WooCommerce 7.6.0 or higher
- Required: SSL - HTTPS must be fully enabled. This is a callback security measure required by Resurs Bank.
- Required: CURL (php-curl) with CURLAUTH_BEARER
- Recommended: Latest stable WordPress release

## External services

This plugin is a payment gateway. To create and manage Resurs payments it must communicate with Resurs Bank AB (publ) external services.

External calls only occur when the plugin is configured with valid Resurs credentials and a Resurs payment flow is used.

**Endpoints**

Production:
[https://merchant-api.resurs.com/](https://merchant-api.resurs.com/)

Test:
[https://merchant-api.integration.resurs.com/](https://merchant-api.integration.resurs.com/)

**Legal**

Terms:
[https://www.resursbank.se/dokument-och-blanketter](https://www.resursbank.se/dokument-och-blanketter)

Privacy policy:
[https://www.resursbank.se/om-oss/integritet-och-sakerhet](https://www.resursbank.se/om-oss/integritet-och-sakerhet)

Customer GDPR information:
[https://www.resursbank.se/om-oss/integritet-och-sakerhet/gdpr-som-kund](https://www.resursbank.se/om-oss/integritet-och-sakerhet/gdpr-som-kund)


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

# 1.2.36

* Separate release for updated changelog wording in 1.2.34. No functional changes.

# 1.2.35

* Version bump only; no functional changes over 1.2.34.

# 1.2.34

* Fixed duplicate Resurs Bank payment sessions being created for a single order when checkout was opened in multiple tabs or retried, only the last session was ever linked to the order (PD-4170).
* Fixed the Resurs Bank connection being removed from an order when checkout was retried after the order had already ended up in an unprocessable state, e.g. a declined credit application (PD-4176).

# 1.2.33

* Updated bundled ECom library to 3.4.2.

# 1.2.32

* Fixed null-pointer issue when `EcomPaymentMethod` is not always present on dashboard installs (PartPayment + WooCommerce utility).
* Removed breaking HTML/CSS sanitization in checkout widget rendering — SDK-generated HTML, CSS and JS now pass through unsanitized as intended (PD-4049).
* Fixed PHP 8.5 compatibility header: max tested PHP version bumped to 8.6 to correctly reflect 8.5 support (PD-4030).

# 1.2.31

* Fixed manually created orders in wp-admin being incorrectly blocked by order status update filters.

# 1.2.29 / 1.2.30

* Fixed incorrect price data being returned in the part payment price checker.

# 1.2.28

* Fixed fatal error caused by missing class `Resursbank\Woocommerce\Admin` on certain install configurations.

# 1.2.27

* Updated bundled ECom library to 3.4.1.
* Fixed CostList row expander resetting to the first section on repeated DOM mutations, overriding user-selected rows in dynamic checkout flows.


== Upgrade Notice ==

Avoid running auto upgrade functions in the platform.
