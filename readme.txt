=== Resurs Bank Payments for WooCommerce ===
Contributors: rbonboarding, RB-Tornevall
Tags: WooCommerce, Resurs Bank, Payment, Payment gateway, ResursBank
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.1
WC requires at least: 7.6.0
WC Tested up to: 10.5.3
Plugin requires ecom: 3.4.0
Plugin tested up to: PHP 8.5
Requires Plugins: woocommerce
Stable tag: 1.2.24
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

# 1.2.24

* Updated bundled ECom to 3.4.0.
* Marked the plugin as tested with PHP 8.5 and aligned related plugin metadata.

# 1.2.23

* Due to constant changes, upgrades may show temporary critical warnings during mixed-version loading, without harming platform operation.
* ECom upgraded to support PHP 8.5.

# 1.2.22

* Incremental commit.

# 1.2.21

* Incremental commit.

# 1.2.20

* WordPress.org review hardening: Implemented additional sanitization/validation at controlled entry points and centralized input handling.
* Improved output escaping for admin notices and inline script payloads where applicable.
* Added wp_kses sanitization for GetAddress widget HTML output in the_content filter callbacks to meet WordPress.org security requirements while preserving widget functionality.
* Removed disallowed HEREDOC/NOWDOC usage flagged by review.
* Removed unneeded development artifacts from the release package.
* Clarified SDK separation by relocating shared SDK path from lib/ecom to vendor/ecom.
* Added/updated inline code comments to document rationale for guideline-driven implementations.
* Plugin Check findings addressed and reduced to minimal, justified remaining notices.
* Fixes and stuff connected to "spammy services" and security advices.

# 1.2.19

* Minor maintenance updates and internal adjustments.

# 1.2.18

* Fixes to reach latest ecom.
* PD-3915: Merge latest ecom with master (NOT the experimental branch)

# 1.2.17

* No changes, only tag bump.

# 1.2.16

* Ecom widget patch.

# 1.2.15

* Positional execution problem (hotfix).
* Can't change order status on other orders than Resurs (hotfix).

== Upgrade Notice ==

Avoid running auto upgrade functions in the platform.
