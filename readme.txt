=== Tornevalls Resurs Bank payment gateway for WooCommerce ===
Contributors: RB-Tornevall, Tornevall
Tags: WooCommerce, Resurs Bank, Payment, Payment gateway, ResursBank, payments, resurs checkout, checkout, RCO, hosted, simplified, hostedflow, simplified flow, hosted flow
Requires at least: 5.5
Tested up to: 5.9
Requires PHP: 7.0
Stable tag: 0.0.1.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Resurs Bank Payment Gateway for WooCommerce.

== Description ==

= Self preservation notice: First time running *should* be a dedicated test environment =

If you are entirely new to this plugin, I'd suggest you to run it in a dedicated test environment that is
supposedly *equal* to a production environment - where you install the plugin live *AFTER* testing.
Primary new problems should be discovered in TEST rather than production since the costs are way lower,
where no real people are depending on failed orders or payments. If something fails in production it also means
that you are the one that potentially looses traffic while your site is down.

This responsibility is yours and this way of handling things is required for *your* safety!

= Description and requirements =

Payment gateway for Resurs Bank AB with support for the most recent shop flows.
SoapClient is required for AfterShop related actions like debiting, crediting, annulments, etc.

There is a publicly available release out supported by Resurs Bank (v2.2). There **may be breaking changes** if you tend
to use **this** plugin as it is an upgrade from the Resurs supported release.

= Requirements =
* WooCommerce: v3.5.0 or higher!
* WordPress: Preferably at least v5.5. It has supported, and probably will, older releases but it is highly
  recommended to go for the latest version as soon as possible if you're not already there.
* HTTPS *must* be enabled in both directions. This is a callback security measure.
* XML and SoapClient must be available.
* Curl is *recommended* but not necessary.
* PHP: [Take a look here](https://docs.woocommerce.com/document/server-requirements/) to keep up with support. As of aug
  2021, both WooCommerce and WordPress is about to jump into 7.4 and higher.
  Also, [read here](https://wordpress.org/news/2019/04/minimum-php-version-update/) for information about lower versions
  of PHP.

Check out [README.md](https://github.com/Tornevall/wpwc-resurs/blob/master/README.md) for more details.


== Installation ==

1. Upload the plugin archive to the "/wp-content/plugins/" directory
2. Activate the plugin through the "Plugins" menu in WordPress
3. Configure the plugin via admin control panel

(Or install and activate the plugin through the WordPress plugin installer)
If you are installing the plugin manually, make sure that the plugin folder contains a folder named includes and that
includes directory are writable, since that's where the payment methods are stored.

== Frequently Asked Questions ==


== Screenshots ==


== Changelog ==


== Upgrade Notice ==

