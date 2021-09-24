# Tornevall Networks Resurs Bank Payment Gateway for WooCommerce

This is a third party plugin written to work as a payment gateway for WooCommerce and WordPress.

## CONTRIBUTION

If you'd like to contribute to this project, you can either sign up to
the [Bitbucket Tracker](https://tracker.tornevall.net/projects/RWC) or create an issue directly
on [github](https://github.com/Tornevall/wpwc-resurs/issues).

## DOCUMENTS AND LINKS

* [The current work-repository as of v0.0](https://bitbucket.tornevall.net/projects/WWW/repos/tornevall-networks-resurs-bank-payment-gateway-for-woocommerce)
  .
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

* HTTPS must be enabled.
* XML and SoapClient must be available.
* Curl is recommended but not necessary.
* PHP: [Take a look here](https://docs.woocommerce.com/document/server-requirements/) to keep up with support. As of aug
  2021, both WooCommerce and WordPress is about to jump into 7.4 and higher.
  Also, [read here](https://wordpress.org/news/2019/04/minimum-php-version-update/) for information about lower versions
  of PHP.
* WooCommerce: v3.4.0 or higher (old features are ditched) and the actual support is set much higher.
* WordPress: v5.5. It has supported, and probably will, older releases but it is highly recommended to upgrade ASAP in
  that case.
* Do not run anything lower than PHP 5.6.20. WordPress recommends 7.4 or greater.

### NEWS AND UPDATES

This plugin do have a wide support for filters and actions, to simplify work for extended developers that need to
connect their own features to whatever they need to do.

## CONFIGURATION

Most of the configuring are made through the admin panel, which includes tweaking when advanced mode is enabled. Take a
look at the documentation for more information.
