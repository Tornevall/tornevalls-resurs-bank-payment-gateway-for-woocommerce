=== Tornevalls Resurs Bank Payment Gateway for WooCommerce ===
Contributors: Tornevall
Tags: WooCommerce, Resurs Bank, Payment, Payment gateway, ResursBank, payments, resurs checkout, checkout, RCO, hosted, simplified, hosted flow, simplified flow
Requires at least: 5.5
Tested up to: 5.9
Requires PHP: 7.0
Stable tag: 0.0.1.3
Plugin URI: https://github.com/Tornevall/wpwc-resurs
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Tornevalls Resurs Bank Payment Gateway for WooCommerce.

== Description ==

[![Crowdin](https://badges.crowdin.net/trwbc/localized.svg)](https://crowdin.com/project/trwbc)

Payment gateway for Resurs Bank AB.

**SoapClient is required** for all actions related to "after shop", debiting, refunding, annulments, and so on. Also, your site has to be fully reachable with SSL/HTTPS. **For full list of requirements, look below.**

There is a publicly available release out supported by Resurs Bank (v2.2). There **may be breaking changes** if you plan to use **this** plugin, as it was an upgrade from the Resurs Bank supported release, since many of the settings and filters have been replaced.



See below for information about requirements.

= WARNING =

**First time running should be a dedicated test environment!**

The main responsibility that this product works properly with your system is yours. For **your** safety you should therefore **TEST the plugin** in a dedicated test environment **before using it** in a production.

If you are entirely new to this plugin or WordPress overall, I'd suggest you to run it in a dedicated test environment that is **equal** to your production environment. Never run any tests in production!

Primary new problems should be discovered in TEST rather than production since the costs are way lower, where no real people are depending on failed orders or payments. If something fails in production it also means that you are the one that potentially looses traffic while your site is down.


= System prerequisites =

* PHP: [Take a look here](https://docs.woocommerce.com/document/server-requirements/) to keep up with support. As of aug 2021, both WooCommerce and WordPress is about to jump into 7.4 and higher. Also, [read here](https://wordpress.org/news/2019/04/minimum-php-version-update/) for information about lower versions of PHP. This plugin is written for 7.0 or higher.
* **Required**: WooCommerce: v3.5.0 or higher!
* **Required**: [SoapClient](https://php.net/manual/en/class.soapclient.php) with xml drivers and extensions.
* **Required**: SSL - HTTPS **must** be **fully** enabled. This is a callback security measure, which is required from Resurs Bank.
* Curl is highly **recommended** but not necessary. We suggest that you do not trust only PHP built in communications as you may loose important features if you run explicitly with streams.
* PHP streams? Yes, you still need them since SoapClient is actually using it.
* WordPress: Preferably at least v5.5. It is highly recommended to go for the latest version as soon as possible if you're not already there. See [here](https://make.wordpress.org/core/handbook/references/php-compatibility-and-wordpress-versions/) for more information.

Check out [README.md](https://github.com/Tornevall/wpwc-resurs/blob/master/README.md) for more details. Documentation for this specific release is currently located at [https://docs.tornevall.net/x/CoC4Aw](https://docs.tornevall.net/x/CoC4Aw)

= Supported shop flows =

* [Simplified Shop Flow](https://test.resurs.com/docs/display/ecom/Simplified+Flow+API). Integrated checkout that works with WooCommerce built in features.
* [Resurs Checkout Web](https://test.resurs.com/docs/display/ecom/Resurs+Checkout+Web). Iframe integration. Currently supporting **RCOv1 and RCOv2**.
* [Hosted Payment Flow](https://test.resurs.com/docs/display/ecom/Hosted+Payment+Flow). A paypal like checkout where most of the payment events takes place at Resurs Bank.



= Contribute =

Help with translation of the plugin by joining [Crowdin](https://crwd.in/trwbc)!

If you'd like to contribute to this project, you can either sign up to [github](https://github.com/Tornevall/wpwc-resurs/issues) and create an issue or use the old [Bitbucket Project](https://tracker.tornevall.net/projects/RWC) to do this on. The full project status can be found [at this dashboard](https://tracker.tornevall.net/secure/Dashboard.jspa?selectPageId=11200) since that's where the project started.


== Installation ==

Preferred Method is to install and activate the plugin through the WordPress plugin installer.

1. Upload the plugin archive to the "/wp-content/plugins/" directory.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Configure the plugin via Resurs Bank control panel in admin.

Are you using this plugin as an upgrade from an older version? No problems! Upgrading is meant to be seamless and should not lead to hard breaking changes. There might be configuration settings to take in consideration to look through, if they are still matching your own. This may be important especially if you have a custom setup.

When you install this plugin and eventually did use an older version, you will also get the opportunity to import the old credentials. For full documentation, take a look at [https://docs.tornevall.net/x/CoC4Aw](https://docs.tornevall.net/x/CoC4Aw)

This plugin release should not be considered an official upgrade from "an older version" as there currently are no old versions available yet - except from the *official release* from Resurs Bank. For the moment, this is not the same thing.


== Frequently Asked Questions ==

= Where can I get more information about this plugin? =

You may visit [docs.tornevall.net](https://docs.tornevall.net/x/CoC4Aw) for more information regarding the plugin. For questions about API and Resurs Bank please visit [test.resurs.com/docs](https://test.resurs.com/docs/).

= Can I upgrade from version 2.2.x? =

**"Version 2.2.x"** is currently the **official Resurs Bank release** and not the same as this release that is a practically a third party reboot. However, the intentions with this plugin is to run as seamless as possible. For example, payments placed with the prior release can be handled by this one.

= What is considered "breaking changes"? =

Breaking changes are collected [here](https://docs.tornevall.net/x/UwJzBQ).

Examples of what could "break" is normally in the form of "no longer supported":

* The prior payment method editor where title and description for each payment method could be edited. This is not really plugin side decided, but based on rules set by Resurs Bank. First of all, titles and descriptions are handled by Resurs Bank to simplify changes that is related to whatever could happen to a payment method. The same goes for the sorting of payment methods in the checkout. Some payment methods is regulated by laws and should be displayed in a certain order. This is no longer up to the plugin to decide and sorting is based on in which order Resurs Bank is returning them in the API. If you want anything changed, related to the payment method, you have to contact Resurs Bank support.
* Many ideas are lifted straight out from the prior version - but not all of them. Remember, this is a third party reboot of an old release. There are settings in this version that is no longer working as before (or at least as expected), especially features that is bound to filters and actions. For actions and filters, you can take a look at the [documentation](https://docs.tornevall.net/x/HoC4Aw). Further information about settings will come.
* Speaking of settings. Settings is almost similar to the old plugin, but with new identifiers. It is partially intentional done, so we don't collide with old settings. Some of them are also not very effective, so some of them has also been removed as they did no longer fill any purpose.

= Is this release a refactored version of Resurs Bank's? =

No. This plugin is a complete reboot. The future intentions might be to replace the other version and **this** release is currently considered a side project.

= Plugin is causing 40X errors on my site =

There are several reasons for the 40X errors, but if they are thrown from an EComPHP API message, there are few things to take in consideration:

* 401 = Unauthorized.
  **Cause**: Bad credentials
  **Solution**: Contact Resurs Bank support for support questions regarding API credentials.
* 403 = Forbidden.
  **Cause**: During testing, this is a bit more common if you are using a server host that is not located in "safe countries".
  **Solution:** Contact Resurs Bank for support.

= What is a EComPHP API Message? =

From time to time, you will notices that errors and exceptions shows up on your screen. Normally, when doing API calls, this is done by [Resurs Bank Ecommerce API for PHP](https://test.resurs.com/docs/pages/viewpage.action?pageId=5014349). Such messages can be traced by Resurs Bank support, if something is unclear but many times error messages are self explained. For more information about this kind of messages can be answered by Resurs Bank support. [You can see some of them here](https://test.resurs.com/docs/display/ecom/Errors%2C+problem+solving+and+corner+cases).

= I see an order but find no information connected to Resurs Bank =

This is a common question about customer actions and how the order has been created/signed. Most of the details is usually placed in the order notes for the order, but if you need more information you could also consider contacting Resurs Bank support.

= How does the respective payment flows work with Resurs Bank in this plugin? =

Full description about how "simplifiedShopFlow", "hosted flow" and "Resurs Checkout" works, not only here, but mostly anywhere can be seen at [https://docs.tornevall.net/x/IAAkBQ](https://docs.tornevall.net/x/IAAkBQ)

== Screenshots ==

1. Primary Basic Settings Configuration page.
2. Part of the Payment Methods View.
3. Part of the Order View.
4. Resurs Checkout Variant 1.

== Changelog ==

Active/open issues can be found [here](https://tracker.tornevall.net/projects/RWC/issues/) [and here](https://github.com/Tornevall/wpwc-resurs/issues). You can also [inspect the project status here](https://tracker.tornevall.net/secure/Dashboard.jspa?selectPageId=11200)! Most recent [CHANGELOG can be found here](https://github.com/Tornevall/wpwc-resurs/blob/master/CHANGELOG.md).

*0.0.1.0 is the first release candidate of what's planned to become 1.0.0.*
Github references should be included for all releases.

= 0.0.1.3 =

* Spelling corrections, translations, etc.

= 0.0.1.2 =

* [RWC-298](https://tracker.tornevall.net/browse/RWC-298) - Extended test mode.
* Content information, readme's, assets and other updates.

= 0.0.1.1 + 1.0.0 =

* [RWC-299](https://tracker.tornevall.net/browse/RWC-299) - Discount handling and buttons on zero orders (adjust)
* [RWC-306](https://tracker.tornevall.net/browse/RWC-306) - Callbacks not properly fetched due to how we handle parameters
* [RWC-300](https://tracker.tornevall.net/browse/RWC-300) - rejected callbacks response handling update.
* [RWC-303](https://tracker.tornevall.net/browse/RWC-303) - Sanitize, Escape, and Validate
* [RWC-304](https://tracker.tornevall.net/browse/RWC-304) - Match text domain with permalink
* [RWC-305](https://tracker.tornevall.net/browse/RWC-305) - enqueue commands for js/css
* [RWC-309](https://tracker.tornevall.net/browse/RWC-309) - Ip control section in support section
* [RWC-310](https://tracker.tornevall.net/browse/RWC-310) - Logging customer events masked

= 0.0.1.0 + 1.0.0 =

**Milestone/Epic -- Release Candidate 1**

* [RWC-6](https://tracker.tornevall.net/browse/RWC-6) - RBWC 0.0.1.0 (Pre-1.0.0) - Milestone Release

**Bug**
* [RWC-22](https://tracker.tornevall.net/browse/RWC-22) - CRITICAL Uncaught Error: Maximum function nesting level of '500' reached
* [RWC-97](https://tracker.tornevall.net/browse/RWC-97) - The blue box may not show properly when payments are not finished
* [RWC-171](https://tracker.tornevall.net/browse/RWC-171) - adminpage_details.phtml bugs out due to RCO.
* [RWC-198](https://tracker.tornevall.net/browse/RWC-198) - Simplified flow does not fill in country on getAddress
* [RWC-200](https://tracker.tornevall.net/browse/RWC-200) - [#7](https://github.com/Tornevall/wpwc-resurs/issues/7): simplified customerdata is not properly created
* [RWC-207](https://tracker.tornevall.net/browse/RWC-207) - The credential validation Button when updating credentials is not present.
* [RWC-223](https://tracker.tornevall.net/browse/RWC-223) - Weird behaviour in order process after delivery tests
* [RWC-229](https://tracker.tornevall.net/browse/RWC-229) - [#25](https://github.com/Tornevall/wpwc-resurs/issues/25): Password validation button disappeared
* [RWC-231](https://tracker.tornevall.net/browse/RWC-231) - Check if this is ours (Trying to get property 'total' of non-object in /usr/local/apache2/htdocs/ecommerceweb.se/woocommerce.ecommerceweb.se/wp-content/plugins/woocommerce/includes/abstracts/abstract-wc-payment-gateway.php on line 270)
* [RWC-232](https://tracker.tornevall.net/browse/RWC-232) - RCO positioning missing a title
* [RWC-233](https://tracker.tornevall.net/browse/RWC-233) - Saving data with credential validation
* [RWC-253](https://tracker.tornevall.net/browse/RWC-253) - Annuity factors with custom currency data
* [RWC-258](https://tracker.tornevall.net/browse/RWC-258) - ECom requesting payments four times in RCO mode

**Task**
* [RWC-3](https://tracker.tornevall.net/browse/RWC-3) - composerize package PSR-4 formatted
* [RWC-7](https://tracker.tornevall.net/browse/RWC-7) - Basic administration Interface
* [RWC-12](https://tracker.tornevall.net/browse/RWC-12) - Generate README that explains architecture and other instructions.
* [RWC-13](https://tracker.tornevall.net/browse/RWC-13) - The plugin needs a basic structure
* [RWC-14](https://tracker.tornevall.net/browse/RWC-14) - Handle or abort deprecated actions and filters.
* [RWC-18](https://tracker.tornevall.net/browse/RWC-18) - Data::getTestMode() should be retreived from environment option
* [RWC-19](https://tracker.tornevall.net/browse/RWC-19) - Add getResursOption for deprecated plugin
* [RWC-20](https://tracker.tornevall.net/browse/RWC-20) - getDeveloperMode should be removed.
* [RWC-21](https://tracker.tornevall.net/browse/RWC-21) - Test plugin with < 3.4.0
* [RWC-24](https://tracker.tornevall.net/browse/RWC-24) - Establish ecom as API
* [RWC-25](https://tracker.tornevall.net/browse/RWC-25) - Is hasCredentials even in use anymore?
* [RWC-26](https://tracker.tornevall.net/browse/RWC-26) - Logging
* [RWC-28](https://tracker.tornevall.net/browse/RWC-28) - Test callback urls before using them (prod only)
* [RWC-29](https://tracker.tornevall.net/browse/RWC-29) - Storm-rearranged classes
* [RWC-42](https://tracker.tornevall.net/browse/RWC-42) - Multiple getaddress (ecom allows SE +NO)
* [RWC-57](https://tracker.tornevall.net/browse/RWC-57) - v3core-tracking
* [RWC-58](https://tracker.tornevall.net/browse/RWC-58) - Order view preparation (works as we have old data compatiblity present)
* [RWC-59](https://tracker.tornevall.net/browse/RWC-59) - Order view credentials
* [RWC-60](https://tracker.tornevall.net/browse/RWC-60) - Checkout: Simplified Shopflow
* [RWC-61](https://tracker.tornevall.net/browse/RWC-61) - Checkout: Hosted Flow
* [RWC-63](https://tracker.tornevall.net/browse/RWC-63) - [#1](https://github.com/Tornevall/wpwc-resurs/issues/1): Implement RCO legacy (postMsg)
* [RWC-64](https://tracker.tornevall.net/browse/RWC-64) - Checkout: Resurs Checkout (facelift) -- HappyFlow
* [RWC-65](https://tracker.tornevall.net/browse/RWC-65) - prepare fraud control flags with actions on bad selections
* [RWC-67](https://tracker.tornevall.net/browse/RWC-67) - Register callbacks
* [RWC-68](https://tracker.tornevall.net/browse/RWC-68) - RCO has its own terms inside iframe
* [RWC-69](https://tracker.tornevall.net/browse/RWC-69) - Show callback statuses in orderview instead of meta data
* [RWC-70](https://tracker.tornevall.net/browse/RWC-70) - Prepare simplified and methods
* [RWC-71](https://tracker.tornevall.net/browse/RWC-71) - Handle annuity factors
* [RWC-74](https://tracker.tornevall.net/browse/RWC-74) - isEnabled (option) should override active status for gateways
* [RWC-75](https://tracker.tornevall.net/browse/RWC-75) - Prevent rounding panic with too few decimals if possible
* [RWC-77](https://tracker.tornevall.net/browse/RWC-77) - signing marked should probably be a timestamp instead of a boolean
* [RWC-78](https://tracker.tornevall.net/browse/RWC-78) - Test coupons
* [RWC-79](https://tracker.tornevall.net/browse/RWC-79) - Add read more data and info for simplified+hosted.
* [RWC-80](https://tracker.tornevall.net/browse/RWC-80) - Test fraudcontrol in simplified
* [RWC-81](https://tracker.tornevall.net/browse/RWC-81) - Add constants for getCheckoutType instead of strings inside code.
* [RWC-82](https://tracker.tornevall.net/browse/RWC-82) - Make sure payment gateways are country based
* [RWC-98](https://tracker.tornevall.net/browse/RWC-98) - Change behaviour output of discount handling as the first part has been handled wrong
* [RWC-99](https://tracker.tornevall.net/browse/RWC-99) - Use native coupon description
* [RWC-102](https://tracker.tornevall.net/browse/RWC-102) - Show all hidden metadata in the box of Resurs information
* [RWC-103](https://tracker.tornevall.net/browse/RWC-103) - Ajax functions on API operation failures
* [RWC-105](https://tracker.tornevall.net/browse/RWC-105) - Support "instant finalizations"
* [RWC-107](https://tracker.tornevall.net/browse/RWC-107) - [#26](https://github.com/Tornevall/wpwc-resurs/issues/26): Unregister callbacks one by one
* [RWC-109](https://tracker.tornevall.net/browse/RWC-109) - Implement aftershop
* [RWC-110](https://tracker.tornevall.net/browse/RWC-110) - Logging of errors should not crash when $return is something else than expected in the flow selector
* [RWC-111](https://tracker.tornevall.net/browse/RWC-111) - Prevent interference with old orders and still allow old plugin handle old orders
* [RWC-113](https://tracker.tornevall.net/browse/RWC-113) - Add information about selected flow in user-agent
* [RWC-114](https://tracker.tornevall.net/browse/RWC-114) - govid should always be shown regardless of fields for getaddress
* [RWC-122](https://tracker.tornevall.net/browse/RWC-122) - Inherit government id from getAddress to resurs form fields
* [RWC-124](https://tracker.tornevall.net/browse/RWC-124) - Log getAddress events
* [RWC-128](https://tracker.tornevall.net/browse/RWC-128) - Custom translations for javascript/template sections
* [RWC-132](https://tracker.tornevall.net/browse/RWC-132) - Avoid locking company field as the chosen customer type as this field is not always updated in session
* [RWC-133](https://tracker.tornevall.net/browse/RWC-133) - Add a spinner to the getaddress button if not already there
* [RWC-136](https://tracker.tornevall.net/browse/RWC-136) - On field submission errors, make sure we translate which fields that is a problem
* [RWC-139](https://tracker.tornevall.net/browse/RWC-139) - Warn for Resurs Bank old gateway payments when old gateway is disabled
* [RWC-141](https://tracker.tornevall.net/browse/RWC-141) - Using getaddress should render setting country if exists. Country is also missing in customersync for RCO
* [RWC-145](https://tracker.tornevall.net/browse/RWC-145) - (Always validate on credential/environmental changes -- monitor updates) Switching between test and production does not validate accounts.
* [RWC-146](https://tracker.tornevall.net/browse/RWC-146) - Annuity factors for DK
* [RWC-148](https://tracker.tornevall.net/browse/RWC-148) - Clarify if card number for "befintligt kort" is mandatory
* [RWC-152](https://tracker.tornevall.net/browse/RWC-152) - Resurs Payment gateway country limitations
* [RWC-153](https://tracker.tornevall.net/browse/RWC-153) - Implement updatePaymentReference in RCO
* [RWC-158](https://tracker.tornevall.net/browse/RWC-158) - Track API history with metadata (?)
* [RWC-159](https://tracker.tornevall.net/browse/RWC-159) - Use filters to change min-max amount based on customizations
* [RWC-161](https://tracker.tornevall.net/browse/RWC-161) - Deprecated functions from ECom 1.3.59 and inspections
* [RWC-163](https://tracker.tornevall.net/browse/RWC-163) - Make sure the cart is always synchronizing in rco
* [RWC-166](https://tracker.tornevall.net/browse/RWC-166) - [#15](https://github.com/Tornevall/wpwc-resurs/issues/15): Checkout: Resurs Checkout (facelift) -- Payment failures
* [RWC-173](https://tracker.tornevall.net/browse/RWC-173) - Add proper extended logging to RCO sessions
* [RWC-174](https://tracker.tornevall.net/browse/RWC-174) - Is this really a proper value?
* [RWC-176](https://tracker.tornevall.net/browse/RWC-176) - Hide getAddress button on unsupported countries.
* [RWC-177](https://tracker.tornevall.net/browse/RWC-177) - When getAddress fields are not present
* [RWC-179](https://tracker.tornevall.net/browse/RWC-179) - Denied payment, change govId, try again (v2)
* [RWC-180](https://tracker.tornevall.net/browse/RWC-180) - [#2](https://github.com/Tornevall/wpwc-resurs/issues/2): Synchronize billing address with getPayment
* [RWC-182](https://tracker.tornevall.net/browse/RWC-182) - Activate script enqueue for RCO only if there is a cart
* [RWC-183](https://tracker.tornevall.net/browse/RWC-183) - [#15](https://github.com/Tornevall/wpwc-resurs/issues/15): Checkout: Resurs Checkout PaymentFail (Legacy)
* [RWC-184](https://tracker.tornevall.net/browse/RWC-184) - [#3](https://github.com/Tornevall/wpwc-resurs/issues/3): Resurs Checkout: Store and use payment method on purchase
* [RWC-185](https://tracker.tornevall.net/browse/RWC-185) - Resurs Checkout Handle failures (signing=>mockfail) -- FailUrl Redirect
* [RWC-186](https://tracker.tornevall.net/browse/RWC-186) - [#4](https://github.com/Tornevall/wpwc-resurs/issues/4): RCOv2 Resurs Checkout: Store and use payment method on purchase
* [RWC-187](https://tracker.tornevall.net/browse/RWC-187) - Make sure we validate AES methods BEFORE using them in wc-api
* [RWC-189](https://tracker.tornevall.net/browse/RWC-189) - [#11](https://github.com/Tornevall/wpwc-resurs/issues/11): setOrderMeta after RCO session should include paymentMethodInformation
* [RWC-190](https://tracker.tornevall.net/browse/RWC-190) - Docs only
* [RWC-191](https://tracker.tornevall.net/browse/RWC-191) - [#5](https://github.com/Tornevall/wpwc-resurs/issues/5): Update initial translations explicitly created during RCO
* [RWC-195](https://tracker.tornevall.net/browse/RWC-195) - [#17](https://github.com/Tornevall/wpwc-resurs/issues/17): setStoreId filter should not be an integer (prepare for future api's)
* [RWC-196](https://tracker.tornevall.net/browse/RWC-196) - do_action at resurs statuses and callbacks
* [RWC-199](https://tracker.tornevall.net/browse/RWC-199) - [#6](https://github.com/Tornevall/wpwc-resurs/issues/6): setOrderMeta should have an insert function
* [RWC-201](https://tracker.tornevall.net/browse/RWC-201) - Facelift: Make sure that payment method is updated, if clicked twice (during denied at first)
* [RWC-202](https://tracker.tornevall.net/browse/RWC-202) - Store last registered callback url locally so that we can see if the urls need to be reupdated
* [RWC-203](https://tracker.tornevall.net/browse/RWC-203) - On admin main front where credentials are set make sure data will be resynched on save
* [RWC-204](https://tracker.tornevall.net/browse/RWC-204) - Test what happens if checkout type is switched in middle of a payment
* [RWC-208](https://tracker.tornevall.net/browse/RWC-208) - When credentials are saved, make sure callbacks are resynched in background
* [RWC-209](https://tracker.tornevall.net/browse/RWC-209) - Monitor saved data to update methods and callbacks on credendial updates
* [RWC-210](https://tracker.tornevall.net/browse/RWC-210) - price variations?
* [RWC-214](https://tracker.tornevall.net/browse/RWC-214) - [#13](https://github.com/Tornevall/wpwc-resurs/issues/13), #8: Refuse to set a status that is already set.
* [RWC-215](https://tracker.tornevall.net/browse/RWC-215) - [#9](https://github.com/Tornevall/wpwc-resurs/issues/9): Necessary callbacks, remove the rest (if not already removed).
* [RWC-216](https://tracker.tornevall.net/browse/RWC-216) - updatePaymentReference and exceptions +logging when it happens
* [RWC-217](https://tracker.tornevall.net/browse/RWC-217) - forceSigning is deprecated.
* [RWC-219](https://tracker.tornevall.net/browse/RWC-219) - [#10](https://github.com/Tornevall/wpwc-resurs/issues/10): According to how RCO works in the docs we probably should change canProcessOrder to avoid conflicts in the payment flow
* [RWC-220](https://tracker.tornevall.net/browse/RWC-220) - [#13](https://github.com/Tornevall/wpwc-resurs/issues/13): Refuse to set a status that is already set in synchronous mode
* [RWC-221](https://tracker.tornevall.net/browse/RWC-221) - [#14](https://github.com/Tornevall/wpwc-resurs/issues/14): During getMetaData-requests, make it possible to fetch getPaymentinfo
* [RWC-224](https://tracker.tornevall.net/browse/RWC-224) - Errors caused by woocommerce thrown to setRbwcGenericError gets double div's for .woocommerce-error
* [RWC-225](https://tracker.tornevall.net/browse/RWC-225) - When activating other delivery address make sure to match the addressrow, to avoid weird addressing
* [RWC-226](https://tracker.tornevall.net/browse/RWC-226) - Make sure that the customer session is really killed after success
* [RWC-227](https://tracker.tornevall.net/browse/RWC-227) - nonces for background processing in wp-admin
* [RWC-228](https://tracker.tornevall.net/browse/RWC-228) - [#23](https://github.com/Tornevall/wpwc-resurs/issues/23): Credential validations by ajax must not activate getOptionsControl.
* [RWC-230](https://tracker.tornevall.net/browse/RWC-230) - Make sure we synchronize order after successful orders with getPayment
* [RWC-234](https://tracker.tornevall.net/browse/RWC-234) - Add error report note when changing credentials and the credentials is failing
* [RWC-236](https://tracker.tornevall.net/browse/RWC-236) - annuityfactors - read more link restoration
* [RWC-237](https://tracker.tornevall.net/browse/RWC-237) - $order->status_update() should be cast into notes that can be identified as the plugin.
* [RWC-241](https://tracker.tornevall.net/browse/RWC-241) - Validate button for credentials fix
* [RWC-242](https://tracker.tornevall.net/browse/RWC-242) - Special feature: getAddress resolve non mocked data
* [RWC-243](https://tracker.tornevall.net/browse/RWC-243) - Move to getDependentSettings plugin-file (This is filter based)
* [RWC-244](https://tracker.tornevall.net/browse/RWC-244) - Admin filter tweak: Override country setting
* [RWC-245](https://tracker.tornevall.net/browse/RWC-245) - Tweak for note prefix might not work properly
* [RWC-246](https://tracker.tornevall.net/browse/RWC-246) - Log frontent-to-backend
* [RWC-247](https://tracker.tornevall.net/browse/RWC-247) - mock mode (fake error in test by intentionally throw errors by config - - time limited enabled.. ex throw on updatePaymentReference
* [RWC-251](https://tracker.tornevall.net/browse/RWC-251) - Orders with orderTotal of 0.
* [RWC-252](https://tracker.tornevall.net/browse/RWC-252) - Log on country change
* [RWC-254](https://tracker.tornevall.net/browse/RWC-254) - Ability to disable annuity factors
* [RWC-255](https://tracker.tornevall.net/browse/RWC-255) - Show supported payment method in annuity factors
* [RWC-256](https://tracker.tornevall.net/browse/RWC-256) - Use updateCheckoutOrderLines as safe layer during "desynched" cart
* [RWC-257](https://tracker.tornevall.net/browse/RWC-257) - Meta data view too big, make toggler
* [RWC-259](https://tracker.tornevall.net/browse/RWC-259) - Queuing callback status updates is the way of handling race conditions
* [RWC-260](https://tracker.tornevall.net/browse/RWC-260) - Handle order statuses from landingpage with queue
* [RWC-261](https://tracker.tornevall.net/browse/RWC-261) - Move getCustomerRealAddress to OrderHandler
* [RWC-262](https://tracker.tornevall.net/browse/RWC-262) - Move Helpers to Service
* [RWC-265](https://tracker.tornevall.net/browse/RWC-265) - Make callbacks handle problematic callbacks
* [RWC-267](https://tracker.tornevall.net/browse/RWC-267) - Removal of callbacks are not warning of "lost callbacks" in backend-admin
* [RWC-268](https://tracker.tornevall.net/browse/RWC-268) - WooTweaker: Ignore digest validations (on technical disturbances)
* [RWC-272](https://tracker.tornevall.net/browse/RWC-272) - Make sure saving credentials also updates callbacks properly
* [RWC-274](https://tracker.tornevall.net/browse/RWC-274) - Set up a better way for how we handled callback exceptions
* [RWC-275](https://tracker.tornevall.net/browse/RWC-275) - Priceinfo errorfixing
* [RWC-281](https://tracker.tornevall.net/browse/RWC-281) - RCO Checkout Error Handling
* [RWC-282](https://tracker.tornevall.net/browse/RWC-282) - Handle timeouts
* [RWC-283](https://tracker.tornevall.net/browse/RWC-283) - Cache annuity factors and payment methods so that they can work independently of Resurs health status
* [RWC-284](https://tracker.tornevall.net/browse/RWC-284) - Unreachable API's handling (AKA Christmas Holidays API Exception patch)
* [RWC-295](https://tracker.tornevall.net/browse/RWC-295) - Handle old plugin orders (but with ability to disable feature)

**Sub-task**
* [RWC-33](https://tracker.tornevall.net/browse/RWC-33) - woocommerce_resurs_bank_' . $type . '_checkout_icon (iconified method)
* [RWC-38](https://tracker.tornevall.net/browse/RWC-38) - resurs_trigger_test_callback
* [RWC-40](https://tracker.tornevall.net/browse/RWC-40) - resursbank_set_storeid
* [RWC-43](https://tracker.tornevall.net/browse/RWC-43) - resurs_getaddress_enabled
* [RWC-48](https://tracker.tornevall.net/browse/RWC-48) - resursbank_custom_annuity_string
* [RWC-52](https://tracker.tornevall.net/browse/RWC-52) - [#16](https://github.com/Tornevall/wpwc-resurs/issues/16): resursbank_temporary_disable_checkout


== Upgrade Notice ==

