# Tornevalls Resurs Bank Payment Gateway for WooCommerce

This is a third party plugin written to work as a payment gateway for WooCommerce and WordPress.

# IMPORTANT -- First time running should be on a dedicated test environment

If you are entirely new to this plugin, I'd suggest you to run it in a dedicated test environment that is supposedly *
equal* to a production environment - where you install the plugin live *AFTER* testing. Primary new problems should be
discovered in TEST rather than production since the costs are way lower, where no real people are depending on failed
orders or payments. If something fails in production it also means that you are the one that potentially looses traffic
while your site is down.

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
* **Required**: SSL - HTTPS **must** be **fully** enabled. This is a callback security measure, which is required from
  Resurs Bank.
* Curl is highly **recommended** but not necessary. We suggest that you do not trust only PHP streams on this one as you
  may loose important features if you run explicitly with streams.
* PHP streams? Yes, you still need them since SoapClient is actually using it.
* WordPress: Preferably at least v5.5. It has supported, and probably will, older releases but it is highly recommended
  to go for the latest version as soon as possible if you're not already there.
  See [here](https://make.wordpress.org/core/handbook/references/php-compatibility-and-wordpress-versions/) for more
  information.

### NEWS AND UPDATES

This plugin do have a wide support for filters and actions, to simplify work for extended developers that need to
connect their own features to whatever they need to do.

## CONFIGURATION

Most of the configuring are made through the admin panel, which includes tweaking when advanced mode is enabled. Take a
look at the documentation for more information.

## Frequently Asked Questions

### Where can I get more information about this plugin? ###

You may visit [docs.tornevall.net](https://docs.tornevall.net/x/CoC4Aw) for more information regarding the plugin. For
questions about API and Resurs Bank please visit [test.resurs.com/docs](https://test.resurs.com/docs/).

## Can I upgrade from version 2.2.x?

**"Version 2.2.x"** is currently the **official Resurs Bank release** and not the same as this release that is a
practically a third party reboot. However, the intentions with this plugin is to run as seamless as possible. For
example, payments placed with the prior release can be handled by this one.

## What is considered "breaking changes"? =

Breaking changes are collected [here](https://docs.tornevall.net/x/UwJzBQ).

Examples of what could "break" is normally in the form of "no longer supported":

* The prior payment method editor where title and description for each payment method could be edited. This is not
  really plugin side decided, but based on rules set by Resurs Bank. First of all, titles and descriptions are handled
  by Resurs Bank to simplify changes that is related to whatever could happen to a payment method. The same goes for the
  sorting of payment methods in the checkout. Some payment methods is regulated by laws and should be displayed in a
  certain order. This is no longer up to the plugin to decide and sorting is based on in which order Resurs Bank is
  returning them in the API. If you want anything changed, related to the payment method, you have to contact Resurs
  Bank support.
* Many ideas are lifted straight out from the prior version - but not all of them. Remember, this is a third party
  reboot of an old release. There are settings in this version that is no longer working as before (or at least as
  expected), especially features that is bound to filters and actions. For actions and filters, you can take a look at
  the [documentation](https://docs.tornevall.net/x/HoC4Aw). Further information about settings will come.
* Speaking of settings. Settings is almost similar to the old plugin, but with new identifiers. It is partially
  intentional done, so we don't collide with old settings. Some of them are also not very effective, so some of them has
  also been removed as they did no longer fill any purpose.

## Is this release a refactored version of Resurs Bank's? =

No. This plugin is a complete reboot. The future intentions might be to replace the other version and **this** release
is currently considered a side project.

### Plugin is causing 40X errors on my site ###

There are several reasons for the 40X errors, but if they are thrown from an EComPHP API message, there are few things
to take in consideration:

* 401 = Unauthorized.
    - **Cause**: Bad credentials
    - **Solution**: Contact Resurs Bank support for support questions regarding API credentials.
* 403 = Forbidden.
    - **Cause**: During testing, this is a bit more common if you are using a server host that is not located in "safe
      countries".
    - **Solution:** Contact Resurs Bank for support.

### What is a EComPHP API Message?

From time to time, you will notices that errors and exceptions shows up on your screen. Normally, when doing API calls,
this is done by [Resurs Bank Ecommerce API for PHP](https://test.resurs.com/docs/pages/viewpage.action?pageId=5014349).
Such messages can be traced by Resurs Bank support, if something is unclear but many times error messages are self
explained. For more information about this kind of messages can be answered by Resurs Bank
support. [You can see some of them here](https://test.resurs.com/docs/display/ecom/Errors%2C+problem+solving+and+corner+cases)
.

## I see an order but find no information connected to Resurs Bank =

This is a common question about customer actions and how the order has been created/signed. Most of the details is
usually placed in the order notes for the order, but if you need more information you could also consider contacting
Resurs Bank support.

## How does the respective payment flows work with Resurs Bank in this plugin? =

Full description about how "simplifiedShopFlow", "hosted flow" and "Resurs Checkout" works, not only here, but mostly
anywhere can be seen at [https://docs.tornevall.net/x/IAAkBQ](https://docs.tornevall.net/x/IAAkBQ)

The payment flow itself for each
API [is described here](https://docs.tornevall.net/display/TORNEVALL/Checkout+workflows+and+metadata+store+described).

