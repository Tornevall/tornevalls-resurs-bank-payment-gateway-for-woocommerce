# Tornevalls Resurs Bank Payment Gateway for WooCommerce

This is a third party plugin written to work as a payment gateway for WooCommerce and WordPress.

# IMPORTANT -- First time running should be on a dedicated test environment

If you are entirely new to this plugin, I'd suggest you to run it in a dedicated test environment that is
supposedly *equal* to a production environment - where you install the plugin live *AFTER* testing.
Primary new problems should be discovered in TEST rather than production since the costs are way lower,
where no real people are depending on failed orders or payments. If something fails in production it also means
that you are the one that potentially looses traffic while your site is down.

This responsibility is yours and this way of handling things is required for *your* safety!

## CONTRIBUTION

If you'd like to contribute to this project, you can either sign up
to [github](https://github.com/Tornevall/wpwc-resurs/issues) and create an issue or use the
old [Bitbucket Tracker](https://tracker.tornevall.net/projects/RWC) to do this on.

## DOCUMENTS AND LINKS

* [Contribute with translations](https://crwd.in/trwbc)
* [The current work-repository as of v0.0](https://bitbucket.tornevall.net/projects/WWW/repos/tornevall-networks-resurs-bank-payment-gateway-for-woocommerce)
* [Github Synchronized repository](https://github.com/Tornevall/wpwc-resurs) will probably be the new official
  repository as bitbucket server will shut down in a few years from now.
* [Documentation](https://docs.tornevall.net/display/TORNEVALL/RBWC+Payment+Gateway).

## DISCLAIMER

### BEWARE OF BREAKING CHANGES

* There is a publicly available release out, supported by Resurs Bank (v2.2). There **may be breaking changes** if you
  tend to use **this** plugin as it is an upgrade from the Resurs supported release.
* This version is not a continued v3.x. It is starting at 1.x, except for during development and as long as it is under
  development (where it starts at 0.0). The plugin should **NOT** be considered an upgrade from a prior release, but a
  whole new release. The intentions with this plugin has been to completely rewrite the codebase and keep some of the
  compatibility, but not the code. If you find that this codebase resides in the Resurs Bank supported repository, it is
  considered v3.x.

### ABOUT THIS RELEASE

The README you're reading right now is considered as a brand new edition, that can also potentially break something if
you tend to handle it as an upgrade from an external repository. It won't break your *site*, but configuration layers
may not be compatible. Many of the filters has, with the actions, been rewritten from scratch and is written to work
closer with other developers.

The external official public repo is located
at [bitbucket](https://bitbucket.tornevall.net/projects/WWW/repos/tornevall-networks-resurs-bank-payment-gateway-for-woocommerce)
and [github](https://github.com/Tornevall/wpwc-resurs). The source code in those repositories **are not supported by
Resurs Bank**. The code that is supported by Resurs
Bank [is always located here](https://bitbucket.org/resursbankplugins/resurs-bank-payment-gateway-for-woocommerce). The
intentions with this plugin has partially been to replace the old release in the future, which also means that it may
become externally supported one day.

The original codebase was initialized july 2020.

## REQUIREMENTS AND SECURITY CONSIDERATIONS

* PHP: [Take a look here](https://docs.woocommerce.com/document/server-requirements/) to keep up with support. As of aug
  2021, both WooCommerce and WordPress is about to jump into 7.4 and higher.
  Also, [read here](https://wordpress.org/news/2019/04/minimum-php-version-update/) for information about lower versions
  of PHP. This plugin is written for 7.0 and higher - and the policy is following WooCommerce *lowest* requirement.
* **Required**: WooCommerce: v3.5.0 or higher!
* **Required**: [SoapClient](https://php.net/manual/en/class.soapclient.php) with xml drivers and extensions.
* **Required**: SSL - HTTPS **must** be **fully** enabled. This is a callback security measure, which is
  required from Resurs Bank.
* Curl is highly **recommended** but not necessary. We suggest that you do not trust only PHP streams on this one
  as you may loose important features if you run explicitly with streams.
* PHP streams? Yes, you still need them since SoapClient is actually using it.
* WordPress: Preferably at least v5.5. It has supported, and probably will, older releases but it is highly
  recommended to go for the latest version as soon as possible if you're not already there.
  See [here](https://make.wordpress.org/core/handbook/references/php-compatibility-and-wordpress-versions/) for more
  information.

### NEWS AND UPDATES

This plugin do have a wide support for filters and actions, to simplify work for extended developers that need to
connect their own features to whatever they need to do.

## CONFIGURATION

Most of the configuring are made through the admin panel, which includes tweaking when advanced mode is enabled. Take a
look at the documentation for more information.

## Frequently Asked Questions

### Where can I get more information about this plugin ###

You may visit https://docs.tornevall.net/display/TORNEVALL/RBWC+Payment+Gateway for more information regarding the
plugin. For questions about API and Resurs Bank please visit https://test.resurs.com/docs/.

### Plugin is causing 40X errors on my site ###

There are several reasons for the 40X errors, but if they are thrown from an EComPHP API message there are few things to take in consideration:

* 401 = Unauthorized.
  **Cause**: Bad credentials
  **Solution**: Contact Resurs Bank support for support questions regarding API credentials.
* 403 = Forbidden.
  **Cause**: This may be more common during test.
  **Solution:** Resolution: Contact Resurs Bank for support.

### What is a EComPHP API Message?

From time to time, you will notices that errors and exceptions shows up on your screen. Normally, when doing API calls, this is done by [Resurs Bank Ecommerce API for PHP](https://test.resurs.com/docs/pages/viewpage.action?pageId=5014349). Such messages can be traced by Resurs Bank support, if something is unclear but many times error messages are self explained. Resurs Bank also have furter information about some error messages. [You can see some of them here](https://test.resurs.com/docs/display/ecom/Errors%2C+problem+solving+and+corner+cases).

### I see an order but find no information connected to Resurs Bank

This is a common question about customer actions and how the order has been created/signed. Most of the details is usually placed in the order notes for the order, but if you need more information you could also consider contacting Resurs Bank support.
