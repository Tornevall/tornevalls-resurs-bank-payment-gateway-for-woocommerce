# 1.2.12

* WOO-1452 Period is reset each time config enters get-store-country
* WOO-1461 README/Changelog updates
* WOO-1464 ECP Timeouts Control
* WOO-1469 Plugin requires ecom 3.2.9 issue.
* WOO-1470 GetPeriods error

# 1.2.11

* WOO-1454 Critical Akamai hotfix, enforcing ecom to resolve on IPv4.


# 1.2.10

* README update for WordPress 6.8 compatibility.

# 1.2.7 - 1.2.9

* WOO-1439 Fixed PHP warnings and notices related to resolving WooCommerce information.
* No changes in the plugin itself, but updates were made to the ecom library to further compact part payment data.

# 1.2.6

* WOO-1434 Fixed issue where switching between stores caused payment method desynchronization.

# 1.2.5

* WOO-1433 Addressed performance issues in cart and checkout processes.

# 1.2.4

* Hotfix for Finnish translation.

# 1.2.3

* WOO-1428 Replaced plugin-based country checks with Location, previously inaccessible from Config::setup.
* WOO-1422 Resolved performance issues and bugs in the cost list.
* WOO-1429 Improved cache handling when switching stores.
* WOO-1431 Plugin can now run even when WooCommerce is disabled.
* WOO-1432 Updated ecom library to show required values in the cost list.

# 1.2.2

* WOO-1426 Fixed widget malfunctions caused by unsupported themes.
* WOO-1427 Added required country checks in React components.

# 1.2.0 - 1.2.1

* WOO-1418 Implemented breaking changes for Part Payment widget.
* WOO-1420 Added new Part Payment Widget and Warning Widget to checkout, in compliance with legal requirements.
* WOO-1423-1425 Refactored PartPayment module scripts and styles for legal compliance.

# 1.1.5

* WOO-1417 Fixed incorrect reset value for PPW period.

# 1.1.1 - 1.1.4

* WOO-1411 About-widget broken.
* WOO-1413 JWT errors may occur during upgrades on some stores.
* WOO-1415 Removed unnecessary sort order for block methods.
* WOO-1416 Slow loading caused by get-address.
* WOO-1414 Fixed false positives in isEnabled.
* Duplicate WOO-1413 entry consolidated.
* Handled uncaught blocks exception.

# 1.1.0

* WOO-1373 Updated update-address/legacy.js under GetAddress module.
* WOO-1379 Verified logged-in customer functionality.
* WOO-1384 Fixed compatibility issue in wp-admin payment method editor.
* WOO-1403 Updated documentation URL in README.
* WOO-1407 Investigated support for Payment Method Management and Sorting in WooCommerce Blocks.
* WOO-1396 Added missing company payment method.
* WOO-1397 Fixed issue where legacy checkout did not reload payment methods.
* WOO-1400 Resolved invalidation of purchase button for certain LEGAL payment methods.
* WOO-1402 Billing address is not sent in deliveryAddress with blocks.
* WOO-1404 Included missing error message from Merchant API.
* WOO-1405 Set threshold value for Finnish accounts to €15.
* WOO-1406 Ensured correct payment methods display in legacy checkout based on country.
* WOO-1409 Included email in payload during order creation.
* WOO-1410 Sent required details (personal ID, email, mobile number) of the responsible person for payment.
* WOO-1412 Fixed issue where PPW widget did not show after resetting values.
* WOO-1378 Tested compatibility with both Blocks and Legacy checkout.

# 1.0.58

* WOO-1390 Fixed part payment widget error when products are out of stock.

# 1.0.57

* Hotfix for missing files in the WordPress repository.

# 1.0.56

* WOO-1246 Default store ID is now resolved via ecom method.
* WOO-1359 Removed `getSiteLanguage` and deprecated store country code option.
* WOO-1366 Cleaned up transient handling for language, country, and default store.
* WOO-1364 Fixed ecom.log error during address fetch at checkout.
* WOO-1381 Added missing type check in gateway icon modifier.

# 1.0.50 - 1.0.55

* Miscellaneous hotfixes.
* WOO-1355 Fixed PPW displaying duplicate or excessive payment methods.
* WOO-1353 Memory exhaustion issue resolved.

# 1.0.49

* WOO-1343 Adjustments for ECP-855 changes.
* WOO-1345 Applied ECP-860 changes.
* WOO-1351 Resolved issue handling non-Resurs payments.

# 1.0.43 - 1.0.48

* Various hotfixes, including issues with live vs. coming soon pages and misplaced script tags.

# 1.0.42

* WOO-1308 Upgraded to version 3.
* WOO-1332 Fixed missing getAddress rendering.
* WOO-1333 Enabled auto-fill of company name on LEGAL entities using gov ID.
* WOO-1337 Raised ecom version tag in WooCommerce.
* WOO-1329 Fixed annuity-factor widget in admin (breaking change for v3).
* WOO-1334 Moved `createPaymentRequest` classes to models.
* WOO-1335 Fixed PPW period loading when selecting payment method.
* WOO-1336 Admin: Store selection no longer breaks on credential changes.
* WOO-1338 Government ID now submitted correctly in `createPayment`.
* WOO-1339 Fixed issues in modify logic.
* WOO-1340 Resolved `null` vs. boolean mismatch in bulk finalization using `getOrder`.
* WOO-1342 Fixed errors in About-widget.

# 1.0.41

* WOO-1330 Continued bug fix and code refinement from previous release.

# 1.0.40

* WOO-1330 Resolved issue where legacy post IDs were not null but falsely assigned in edge cases.

# 1.0.39

* WOO-1312 Implemented support for High Performance Order Storage.
* WOO-1327 Adjusted icon positioning (logotypes now float to the right).

# 1.0.38

* Hotfix for MAPI overload in `getPayment`.

# 1.0.37

* WOO-1324 Removed false log errors for `getPriceSignage`.
* WOO-1322 Fixed error when bulk charging frozen orders.
* WOO-1323 Prevented race conditions between thank you page and order callbacks.
* WOO-1326 Resolved performance issue related to `canCapture` in order list.

# 1.0.36

* WOO-1317 Handled orders marked as rejected and older than 30 days.
* WOO-1319 Checked for hardcoded VAT rates.
* WOO-1315 Resolved order list crashes for capturable orders.
* WOO-1318 Allowed order status changes even with Resurs order management deactivated.
* WOO-1321 Fixed capture issues due to modify being disabled.

# 1.0.35

* WOO-1315 Fixed issue where capturable orders caused crashes in the order list.

# 1.0.34

* WOO-1313 Programmatic order updates now correctly cancel frozen payments.

# 1.0.33

* Reverted changes from the previous release that modified failed-status setup.

# 1.0.32

* WOO-1310 Resolved issue with double cancellation of frozen and rejected orders.

# 1.0.31

* WOO-1306 Added support for limiting payment methods by country.

# 1.0.29 - 1.0.30

* Identified and addressed issues in composer.json configuration.

# 1.0.28

* Hotfix for translation issues.

# 1.0.27 (ECom2-2.x)

* WOO-1291 Added plugin and platform metadata.
* WOO-1293 Verified WooCommerce with ECP-636.
* WOO-1295 Completed support for credit-denied status.
* WOO-1299 Added X-close modal button at payment method level in checkout.
* WOO-1302 Implemented updated changes for Payment Information module (ecom2-v2).
* WOO-1305 Released Finnish translation update for part payment string.
* WOO-1294 Implemented locale/language changes for ecom2 v2.0.
* WOO-1297 Resolved callback testing issues.
* WOO-1298 Fixed layout issues in cost info display.
* WOO-1300 Resolved annuity reload issues.
* WOO-1304 Fixed issue fetching product data in part payment price info section.

# 1.0.24 - 1.0.26

* WOO-1305 Applied Finnish translation for part payment information.

# 1.0.23

* Only tag changed.

# 1.0.22

* WOO-1292 Added Norwegian base translations for WooCommerce.

# 1.0.21

* WOO-1290 Fixed layout issues in mobile view for price info.

# 1.0.20

* WOO-1289 Set z-index for payment information widget to 999999 for proper layering.

# 1.0.19

* WOO-1288 Added Norwegian part payment info text.

# 1.0.18

* WOO-1285 Fixed uninitialized static property error in Resurs Config class.
* WOO-1283 Reworked locale handling.

# 1.0.17

* WOO-1283 Implemented Finnish/Norwegian translations for checkout.

# 1.0.16

* WOO-1282 Disabled getAddress widget for non-SE stores.

# 1.0.15

* WOO-1279 Verified plugin with rcoplus branch and WooCommerce 8.5.0.
* WOO-1280 Fixed undefined array key warnings in checkout/wp-admin.

# 1.0.14

* Rebuilt commit for internal consistency.

# 1.0.13

* WOO-1274 Enabled gateway sort order configuration based on wp-admin settings.
* WOO-1267 Fixed compatibility issues with WooCommerce 8.0.x.
* WOO-1276 Corrected CSS malfunction in "Read more" section on custom themes.

# 1.0.12

* WOO-1275 Resolved script errors in part payment display for variable products.

# 1.0.11

* WOO-1271 Fixed excessive calls to `/price_signage?amount=0`.
* WOO-1272 Updated rules for payment widget rendering.
* WOO-1273 Fixed customer type switching logic.

# 1.0.10

* WOO-1270 Enforced required input field for company ID.

# 1.0.9

* WOO-1264 Improved admin and checkout display logic for payment gateways.
* WOO-1265 Fixed redirect issue from "payments" section.
* WOO-1261 Fixed null call in `get_query_var`.
* WOO-1266 Suppressed warnings for missing min/max gateway methods.
* WOO-1268 Fixed admin widget conflict in part payment.

# 1.0.8

* WOO-1261 Fixed null call in `get_query_var`.

# 1.0.7

* WOO-1260 Fixed return type inconsistency in payment status mapping.

# 1.0.6

* WOO-1249 Resolved store timeouts causing site inaccessibility.

# 1.0.5 (ECom Upgrade)

* WOO-1257 Fixed incorrect USP strings when translations are missing.

# 1.0.4 (ECom Upgrade)

* WOO-1252 Extended description length in ecom package.
* WOO-1250 Improved logging and error handling for getStores.
* WOO-1253 Fixed store selection box error.
* WOO-1254 Added message box in Resurs settings.
* WOO-1255 Fixed store fetcher functionality.

# 1.0.3

* WOO-1250 Improved getStores error logging.

# 1.0.2

* WOO-1248 Fixed issue preventing switch to production mode.

# 1.0.0 - 1.0.1

Initial release with full MAPI integration, country and language handling, gateway centralization, logging, order
handling, and more.

* WOO-547 Replace method name in confirmation messages on screen if possible.
* WOO-640 Resolved domain conflict: changed language domain to Resurs.
* WOO-641 Adapted plugin slug and readme to match Resurs naming.
* WOO-687 Fixed default installations where decimals were set to 0.
* WOO-705 Implemented MAPI config: Client/Secret.
* WOO-706 Implemented MAPI getAddress.
* WOO-707 Initialized ecom2 configuration.
* WOO-708 Managed store selection in wp-admin.
* WOO-710 Implemented getPayment (resumed for bug fixing).
* WOO-712 Centralized handling of payment methods in same interface.
* WOO-715 Clarified which API is used in all calls.
* WOO-716 Implemented createPayment (main task).
* WOO-718 Integrated payment methods in wp-admin.
* WOO-721 Removed all MAPI traces from main plugin for version 3.0.
* WOO-722 Disabled SOAP usage for getPaymentMethods when MAPI is active.
* WOO-724 Upgraded server for ecom2 and tested plugin on WC4.
* WOO-729 Translated MAPI-related content to Swedish.
* WOO-730 Migrated MAPI credentials from SOAP settings and removed MAPI tab.
* WOO-731 Automated MAPI getPaymentMethods.
* WOO-733 Ensured plugin passes WordPress plugin review.
* WOO-736 Displayed USPs in checkout.
* WOO-738 Enabled "Read more" for part payment and annuity widgets.
* WOO-739 Expanded getPaymentMethods cache with extended model for purchases.
* WOO-742 Implemented ecom2-based getPayment box.
* WOO-743 Merged MAPI functionality into main plugin, raised PHP requirement to 8.1.
* WOO-744 Renamed canLog to centralize logging control.
* WOO-746 Removed RCO support.
* WOO-747 Added bundled requirements for ecom2.
* WOO-749 Integrated MAPI to unify with ResursDefault gateway.
* WOO-750 Deprecated processHosted (no longer usable with MAPI).
* WOO-753 Removed registration/display of SOAP callbacks.
* WOO-755 Validated account with jwt-getStores instead of SOAP.
* WOO-757 Ensured store fetcher functions properly with incomplete data.
* WOO-762 Verified getAddress post module import.
* WOO-763 Ensured account data was preserved correctly during conversion from v2.2 to jwt.
* WOO-775 Selected first store ID on initial store list generation.
* WOO-780 Disabled setpreferredflow (no longer exists in ecom2).
* WOO-781 Disabled "three flags" due to behavioral changes in ecom2.
* WOO-790 Replaced log options with single log path config.
* WOO-791 Managed Data::logger instance.
* WOO-792 Implemented payment method list rendering.
* WOO-806 Improved error handling in getJwt.
* WOO-807 Corrected use of ecom1 for quantityUnit.
* WOO-814 Made externalCustomerId nullable in MAPI metadata.
* WOO-816 Used transient as cache for payment methods.
* WOO-822 Restored admin functionality for payment methods.
* WOO-823 Completed first part of create API to handle orders fully.
* WOO-824 Fully removed RCO support.
* WOO-829 Fixed logging for ecom2 to always have instance.
* WOO-832 Changed store ID saving logic when only one store is available.
* WOO-839 Transferred translation responsibilities to ecom2 (e.g. "Please choose store").
* WOO-841 Enabled loglevel-based logging in ecom.
* WOO-842 Updated locales (ECP-251).
* WOO-844 Used getDefaults from getData values.
* WOO-854 Replaced production check with !Config::isProduction.
* WOO-856 Removed all dependencies on ecom1.
* WOO-857 Added callbacks and URLs for MAPI create.
* WOO-860 Implemented getAddress endpoint.
