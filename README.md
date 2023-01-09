# Resurs Bank Plus Payment Gateway for WooCommerce #

This is a payment gateway for WooCommerce and WordPress. Do not run this plugin side by side with the prior releases of Resurs Bank plugins!
Requires PHP 8.1

# IMPORTANT -- First time running should be on a dedicated test environment #

If you are entirely new to this plugin, I'd suggest you to run it in a dedicated test environment that is supposedly *
equal* to a production environment - where you install the plugin live *AFTER* testing. Primary new problems should be
discovered in TEST rather than production since the costs are way lower, where no real people are depending on failed
orders or payments. If something fails in production it also means that you are the one that potentially looses traffic
while your site is down.

This responsibility is yours and this way of handling things is required for *your* safety!

## CONTRIBUTION ##

### ABOUT THIS RELEASE ###

The README you're reading right now is considered belonging to a brand new version, that can also potentially break something if
you tend to handle it as an upgrade from the older plugin (that currently is at v2.2). Running them side by side can also break things badly.

## REQUIREMENTS AND SECURITY CONSIDERATIONS ##

* **Required**: PHP: 8.1 or later.
* **Required**: WooCommerce: v3.5.0 or higher - preferably *always* the latest release!
* **Required**: SSL - HTTPS **must** be **fully** enabled. This is a callback security measure, which is required from
  Resurs Bank.
* **Required**: CURL (php-curl).
* WordPress: Preferably simply the latest release. It is highly recommended to go for the latest version as soon as
  possible if you're not already there.
  See [here](https://make.wordpress.org/core/handbook/references/php-compatibility-and-wordpress-versions/) for more
  information.

## CONFIGURATION ##

Configuration are made through the admin panel.

## Frequently Asked Questions ##

### Where can I get more information about this plugin? ###

In the documentation of Resurs.

## Can I upgrade from version 2.2.x? ##

No.

# Screenshots from the plugin #

