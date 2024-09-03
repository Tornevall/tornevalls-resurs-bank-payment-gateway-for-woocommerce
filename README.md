# Resurs Bank Payments for WooCommerce #

This is a payment gateway for WooCommerce and WordPress. Do not run this plugin side by side with the prior releases of Resurs Bank plugins!
Requires PHP 8.1

# IMPORTANT -- First time running should be on a dedicated test environment #

A payment is expected to be simple, secure and fast, regardless of whether it takes place in a physical store or online. With over 6 million customers around the Nordics, we make sure to be up-to-date with smart payment solutions where customers shop.

At checkout, your customer can choose between several flexible payment options, something that not only provides a better shopping experience but also generates more and larger purchases.

### ABOUT THIS RELEASE ###

The README you're reading right now is considered belonging to a brand new version, that can also potentially break something if
you tend to handle it as an upgrade from the older plugin (that currently is at v2.2). Running them side by side can also break things badly.

## REQUIREMENTS AND SECURITY CONSIDERATIONS ##

* **Required**: PHP 8.1 or higher.
* **Required**: WooCommerce: At least v7.6.0
* **Required**: SSL - HTTPS **must** be **fully** enabled. This is a callback security measure, which is required from Resurs Bank.
* **Required**: CURL (php-curl).
* WordPress: Preferably simply the latest release. It is highly recommended to go for the latest version as soon as possible if you're not already there. See [here](https://make.wordpress.org/core/handbook/references/php-compatibility-and-wordpress-versions/) for more information.

## CONFIGURATION ##

Configuration are made through the admin panel.

## Frequently Asked Questions ##

### Where can I get more information about this plugin? ###

Find out more in about the plugin [in our documentation](https://developers.resurs.com/platform-plugins/woocommerce/resurs-merchant-api-2.0-for-woocommerce/).

[Sign up for Resurs](https://www.resursbank.se/betallosningar).

## Can I upgrade from version 2.2.x? ##

No (this plugin is a breaking change). But if you've used the old version before, historical payments are transparent and can be handled by this new release.
If you wish to upgrade from the old plugin release, you need to contact Resurs Bank for new credentials.
Also be aware of that you need new credentials when switching from the "2.2-package".
