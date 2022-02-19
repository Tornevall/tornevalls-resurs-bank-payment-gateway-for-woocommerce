# CHANGELOG -- *trbwc series*.

_0.0.1.0 is the first release candidate of what's planned to become 1.0.0._
Github references should be included for all releases.

# 0.0.1.4 + 1.0.0

* [RWC-315](https://tracker.tornevall.net/browse/RWC-315) - Bug: Logging obfuscated data causes log view unviewable
* [RWC-316](https://tracker.tornevall.net/browse/RWC-316) - Bug: get_cost_of_purchase default html view template includes wp_head which causes problems on sanitation
* [RWC-326](https://tracker.tornevall.net/browse/RWC-326) - Bug: Part payment settings don't seem to save 
* [RWC-314](https://tracker.tornevall.net/browse/RWC-314) - Task: Clean up old settings button
* [RWC-319](https://tracker.tornevall.net/browse/RWC-319) - Task: ip checking feature should also request a soapdocument
* [RWC-322](https://tracker.tornevall.net/browse/RWC-322) - Task: Timestamp last updated callbacks/payment methods
* [RWC-323](https://tracker.tornevall.net/browse/RWC-323) - Task: Callback trigger must keep its "last received"
* [RWC-324](https://tracker.tornevall.net/browse/RWC-324) - Task: Bug-ish in WooCommerce 6.2.0 causes our admin-layout to break
* [RWC-325](https://tracker.tornevall.net/browse/RWC-325) - Task: Check expected versions before allowing complete run

# 0.0.1.3 + 1.0.0

* Spelling corrections, translations, etc.

# 0.0.1.2 + 1.0.0

* [RWC-298](https://tracker.tornevall.net/browse/RWC-298) - Extended test mode.
* Content information, readme's, assets and other updates.

# 0.0.1.1 + 1.0.0

* [RWC-299](https://tracker.tornevall.net/browse/RWC-299) - Discount handling and buttons on zero orders (adjust)
* [RWC-306](https://tracker.tornevall.net/browse/RWC-306) - Callbacks not properly fetched due to how we handle parameters
* [RWC-300](https://tracker.tornevall.net/browse/RWC-300) - rejected callbacks response handling update.
* [RWC-303](https://tracker.tornevall.net/browse/RWC-303) - Sanitize, Escape, and Validate
* [RWC-304](https://tracker.tornevall.net/browse/RWC-304) - Match text domain with permalink
* [RWC-305](https://tracker.tornevall.net/browse/RWC-305) - enqueue commands for js/css
* [RWC-309](https://tracker.tornevall.net/browse/RWC-309) - Ip control section in support section
* [RWC-310](https://tracker.tornevall.net/browse/RWC-310) - Logging customer events masked

# 0.0.1.0 + 1.0.0

**Milestone/Epic -- Release Candidate 1**

* [RWC-6](https://tracker.tornevall.net/browse/RWC-6) - RBWC 0.0.1.0 (Pre-1.0.0) - Milestone Release

**Bug**

* [RWC-22](https://tracker.tornevall.net/browse/RWC-22) - CRITICAL Uncaught Error: Maximum function nesting level of '
  500' reached
* [RWC-97](https://tracker.tornevall.net/browse/RWC-97) - The blue box may not show properly when payments are not
  finished
* [RWC-171](https://tracker.tornevall.net/browse/RWC-171) - adminpage_details.phtml bugs out due to RCO.
* [RWC-198](https://tracker.tornevall.net/browse/RWC-198) - Simplified flow does not fill in country on getAddress
* [RWC-200](https://tracker.tornevall.net/browse/RWC-200) - [#7](https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce/issues/7):
  simplified customerdata is not properly created
* [RWC-207](https://tracker.tornevall.net/browse/RWC-207) - The credential validation Button when updating credentials
  is not present.
* [RWC-223](https://tracker.tornevall.net/browse/RWC-223) - Weird behaviour in order process after delivery tests
* [RWC-229](https://tracker.tornevall.net/browse/RWC-229) - [#25](https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce/issues/25):
  Password validation button disappeared
* [RWC-231](https://tracker.tornevall.net/browse/RWC-231) - Check if this is ours (Trying to get property 'total' of
  non-object in
  /usr/local/apache2/htdocs/ecommerceweb.se/woocommerce.ecommerceweb.se/wp-content/plugins/woocommerce/includes/abstracts/abstract-wc-payment-gateway.php
  on line 270)
* [RWC-232](https://tracker.tornevall.net/browse/RWC-232) - RCO positioning missing a title
* [RWC-233](https://tracker.tornevall.net/browse/RWC-233) - Saving data with credential validation
* [RWC-253](https://tracker.tornevall.net/browse/RWC-253) - Annuity factors with custom currency data
* [RWC-258](https://tracker.tornevall.net/browse/RWC-258) - ECom requesting payments four times in RCO mode

**Task**

* [RWC-3](https://tracker.tornevall.net/browse/RWC-3) - composerize package PSR-4 formatted
* [RWC-7](https://tracker.tornevall.net/browse/RWC-7) - Basic administration Interface
* [RWC-12](https://tracker.tornevall.net/browse/RWC-12) - Generate README that explains architecture and other
  instructions.
* [RWC-13](https://tracker.tornevall.net/browse/RWC-13) - The plugin needs a basic structure
* [RWC-14](https://tracker.tornevall.net/browse/RWC-14) - Handle or abort deprecated actions and filters.
* [RWC-18](https://tracker.tornevall.net/browse/RWC-18) - Data::getTestMode() should be retreived from environment
  option
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
* [RWC-58](https://tracker.tornevall.net/browse/RWC-58) - Order view preparation (works as we have old data compatiblity
  present)
* [RWC-59](https://tracker.tornevall.net/browse/RWC-59) - Order view credentials
* [RWC-60](https://tracker.tornevall.net/browse/RWC-60) - Checkout: Simplified Shopflow
* [RWC-61](https://tracker.tornevall.net/browse/RWC-61) - Checkout: Hosted Flow
* [RWC-63](https://tracker.tornevall.net/browse/RWC-63) - [#1](https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce/issues/1):
  Implement RCO legacy (postMsg)
* [RWC-64](https://tracker.tornevall.net/browse/RWC-64) - Checkout: Resurs Checkout (facelift) -- HappyFlow
* [RWC-65](https://tracker.tornevall.net/browse/RWC-65) - prepare fraud control flags with actions on bad selections
* [RWC-67](https://tracker.tornevall.net/browse/RWC-67) - Register callbacks
* [RWC-68](https://tracker.tornevall.net/browse/RWC-68) - RCO has its own terms inside iframe
* [RWC-69](https://tracker.tornevall.net/browse/RWC-69) - Show callback statuses in orderview instead of meta data
* [RWC-70](https://tracker.tornevall.net/browse/RWC-70) - Prepare simplified and methods
* [RWC-71](https://tracker.tornevall.net/browse/RWC-71) - Handle annuity factors
* [RWC-74](https://tracker.tornevall.net/browse/RWC-74) - isEnabled (option) should override active status for gateways
* [RWC-75](https://tracker.tornevall.net/browse/RWC-75) - Prevent rounding panic with too few decimals if possible
* [RWC-77](https://tracker.tornevall.net/browse/RWC-77) - signing marked should probably be a timestamp instead of a
  boolean
* [RWC-78](https://tracker.tornevall.net/browse/RWC-78) - Test coupons
* [RWC-79](https://tracker.tornevall.net/browse/RWC-79) - Add read more data and info for simplified+hosted.
* [RWC-80](https://tracker.tornevall.net/browse/RWC-80) - Test fraudcontrol in simplified
* [RWC-81](https://tracker.tornevall.net/browse/RWC-81) - Add constants for getCheckoutType instead of strings inside
  code.
* [RWC-82](https://tracker.tornevall.net/browse/RWC-82) - Make sure payment gateways are country based
* [RWC-98](https://tracker.tornevall.net/browse/RWC-98) - Change behaviour output of discount handling as the first part
  has been handled wrong
* [RWC-99](https://tracker.tornevall.net/browse/RWC-99) - Use native coupon description
* [RWC-102](https://tracker.tornevall.net/browse/RWC-102) - Show all hidden metadata in the box of Resurs information
* [RWC-103](https://tracker.tornevall.net/browse/RWC-103) - Ajax functions on API operation failures
* [RWC-105](https://tracker.tornevall.net/browse/RWC-105) - Support "instant finalizations"
* [RWC-107](https://tracker.tornevall.net/browse/RWC-107) - [#26](https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce/issues/26):
  Unregister callbacks one by one
* [RWC-109](https://tracker.tornevall.net/browse/RWC-109) - Implement aftershop
* [RWC-110](https://tracker.tornevall.net/browse/RWC-110) - Logging of errors should not crash when $return is something
  else than expected in the flow selector
* [RWC-111](https://tracker.tornevall.net/browse/RWC-111) - Prevent interference with old orders and still allow old
  plugin handle old orders
* [RWC-113](https://tracker.tornevall.net/browse/RWC-113) - Add information about selected flow in user-agent
* [RWC-114](https://tracker.tornevall.net/browse/RWC-114) - govid should always be shown regardless of fields for
  getaddress
* [RWC-122](https://tracker.tornevall.net/browse/RWC-122) - Inherit government id from getAddress to resurs form fields
* [RWC-124](https://tracker.tornevall.net/browse/RWC-124) - Log getAddress events
* [RWC-128](https://tracker.tornevall.net/browse/RWC-128) - Custom translations for javascript/template sections
* [RWC-132](https://tracker.tornevall.net/browse/RWC-132) - Avoid locking company field as the chosen customer type as
  this field is not always updated in session
* [RWC-133](https://tracker.tornevall.net/browse/RWC-133) - Add a spinner to the getaddress button if not already there
* [RWC-136](https://tracker.tornevall.net/browse/RWC-136) - On field submission errors, make sure we translate which
  fields that is a problem
* [RWC-139](https://tracker.tornevall.net/browse/RWC-139) - Warn for Resurs Bank old gateway payments when old gateway
  is disabled
* [RWC-141](https://tracker.tornevall.net/browse/RWC-141) - Using getaddress should render setting country if exists.
  Country is also missing in customersync for RCO
* [RWC-145](https://tracker.tornevall.net/browse/RWC-145) - (Always validate on credential/environmental changes --
  monitor updates) Switching between test and production does not validate accounts.
* [RWC-146](https://tracker.tornevall.net/browse/RWC-146) - Annuity factors for DK
* [RWC-148](https://tracker.tornevall.net/browse/RWC-148) - Clarify if card number for "befintligt kort" is mandatory
* [RWC-152](https://tracker.tornevall.net/browse/RWC-152) - Resurs Payment gateway country limitations
* [RWC-153](https://tracker.tornevall.net/browse/RWC-153) - Implement updatePaymentReference in RCO
* [RWC-158](https://tracker.tornevall.net/browse/RWC-158) - Track API history with metadata (?)
* [RWC-159](https://tracker.tornevall.net/browse/RWC-159) - Use filters to change min-max amount based on customizations
* [RWC-161](https://tracker.tornevall.net/browse/RWC-161) - Deprecated functions from ECom 1.3.59 and inspections
* [RWC-163](https://tracker.tornevall.net/browse/RWC-163) - Make sure the cart is always synchronizing in rco
* [RWC-166](https://tracker.tornevall.net/browse/RWC-166) - [#15](https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce/issues/15):
  Checkout: Resurs Checkout (facelift) -- Payment failures
* [RWC-173](https://tracker.tornevall.net/browse/RWC-173) - Add proper extended logging to RCO sessions
* [RWC-174](https://tracker.tornevall.net/browse/RWC-174) - Is this really a proper value?
* [RWC-176](https://tracker.tornevall.net/browse/RWC-176) - Hide getAddress button on unsupported countries.
* [RWC-177](https://tracker.tornevall.net/browse/RWC-177) - When getAddress fields are not present
* [RWC-179](https://tracker.tornevall.net/browse/RWC-179) - Denied payment, change govId, try again (v2)
* [RWC-180](https://tracker.tornevall.net/browse/RWC-180) - [#2](https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce/issues/2):
  Synchronize billing address with getPayment
* [RWC-182](https://tracker.tornevall.net/browse/RWC-182) - Activate script enqueue for RCO only if there is a cart
* [RWC-183](https://tracker.tornevall.net/browse/RWC-183) - [#15](https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce/issues/15):
  Checkout: Resurs Checkout PaymentFail (Legacy)
* [RWC-184](https://tracker.tornevall.net/browse/RWC-184) - [#3](https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce/issues/3):
  Resurs Checkout: Store and use payment method on purchase
* [RWC-185](https://tracker.tornevall.net/browse/RWC-185) - Resurs Checkout Handle failures (signing=>mockfail) --
  FailUrl Redirect
* [RWC-186](https://tracker.tornevall.net/browse/RWC-186) - [#4](https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce/issues/4):
  RCOv2 Resurs Checkout: Store and use payment method on purchase
* [RWC-187](https://tracker.tornevall.net/browse/RWC-187) - Make sure we validate AES methods BEFORE using them in
  wc-api
* [RWC-189](https://tracker.tornevall.net/browse/RWC-189) - [#11](https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce/issues/11):
  setOrderMeta after RCO session should include paymentMethodInformation
* [RWC-190](https://tracker.tornevall.net/browse/RWC-190) - Docs only
* [RWC-191](https://tracker.tornevall.net/browse/RWC-191) - [#5](https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce/issues/5):
  Update initial translations explicitly created during RCO
* [RWC-195](https://tracker.tornevall.net/browse/RWC-195) - [#17](https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce/issues/17):
  setStoreId filter should not be an integer (prepare for future api's)
* [RWC-196](https://tracker.tornevall.net/browse/RWC-196) - do_action at resurs statuses and callbacks
* [RWC-199](https://tracker.tornevall.net/browse/RWC-199) - [#6](https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce/issues/6):
  setOrderMeta should have an insert function
* [RWC-201](https://tracker.tornevall.net/browse/RWC-201) - Facelift: Make sure that payment method is updated, if
  clicked twice (during denied at first)
* [RWC-202](https://tracker.tornevall.net/browse/RWC-202) - Store last registered callback url locally so that we can
  see if the urls need to be reupdated
* [RWC-203](https://tracker.tornevall.net/browse/RWC-203) - On admin main front where credentials are set make sure data
  will be resynched on save
* [RWC-204](https://tracker.tornevall.net/browse/RWC-204) - Test what happens if checkout type is switched in middle of
  a payment
* [RWC-208](https://tracker.tornevall.net/browse/RWC-208) - When credentials are saved, make sure callbacks are
  resynched in background
* [RWC-209](https://tracker.tornevall.net/browse/RWC-209) - Monitor saved data to update methods and callbacks on
  credendial updates
* [RWC-210](https://tracker.tornevall.net/browse/RWC-210) - price variations?
* [RWC-214](https://tracker.tornevall.net/browse/RWC-214) - [#13](https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce/issues/13),
  [#8](https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce/issues/8): Refuse to set a status that is already set.
* [RWC-215](https://tracker.tornevall.net/browse/RWC-215) - [#9](https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce/issues/9):
  Necessary callbacks, remove the rest (if not already removed).
* [RWC-216](https://tracker.tornevall.net/browse/RWC-216) - updatePaymentReference and exceptions +logging when it
  happens
* [RWC-217](https://tracker.tornevall.net/browse/RWC-217) - forceSigning is deprecated.
* [RWC-219](https://tracker.tornevall.net/browse/RWC-219) - [#10](https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce/issues/10):
  According to how RCO works in the docs we probably should change canProcessOrder to avoid conflicts in the payment
  flow
* [RWC-220](https://tracker.tornevall.net/browse/RWC-220) - [#13](https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce/issues/13):
  Refuse to set a status that is already set in synchronous mode
* [RWC-221](https://tracker.tornevall.net/browse/RWC-221) - [#14](https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce/issues/14):
  During getMetaData-requests, make it possible to fetch getPaymentinfo
* [RWC-224](https://tracker.tornevall.net/browse/RWC-224) - Errors caused by woocommerce thrown to setRbwcGenericError
  gets double div's for .woocommerce-error
* [RWC-225](https://tracker.tornevall.net/browse/RWC-225) - When activating other delivery address make sure to match
  the addressrow, to avoid weird addressing
* [RWC-226](https://tracker.tornevall.net/browse/RWC-226) - Make sure that the customer session is really killed after
  success
* [RWC-227](https://tracker.tornevall.net/browse/RWC-227) - nonces for background processing in wp-admin
* [RWC-228](https://tracker.tornevall.net/browse/RWC-228) - [#23](https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce/issues/23):
  Credential validations by ajax must not activate getOptionsControl.
* [RWC-230](https://tracker.tornevall.net/browse/RWC-230) - Make sure we synchronize order after successful orders with
  getPayment
* [RWC-234](https://tracker.tornevall.net/browse/RWC-234) - Add error report note when changing credentials and the
  credentials is failing
* [RWC-236](https://tracker.tornevall.net/browse/RWC-236) - annuityfactors - read more link restoration
* [RWC-237](https://tracker.tornevall.net/browse/RWC-237) - $order->status_update() should be cast into notes that can
  be identified as the plugin.
* [RWC-241](https://tracker.tornevall.net/browse/RWC-241) - Validate button for credentials fix
* [RWC-242](https://tracker.tornevall.net/browse/RWC-242) - Special feature: getAddress resolve non mocked data
* [RWC-243](https://tracker.tornevall.net/browse/RWC-243) - Move to getDependentSettings plugin-file (This is filter
  based)
* [RWC-244](https://tracker.tornevall.net/browse/RWC-244) - Admin filter tweak: Override country setting
* [RWC-245](https://tracker.tornevall.net/browse/RWC-245) - Tweak for note prefix might not work properly
* [RWC-246](https://tracker.tornevall.net/browse/RWC-246) - Log frontent-to-backend
* [RWC-247](https://tracker.tornevall.net/browse/RWC-247) - mock mode (fake error in test by intentionally throw errors
  by config - - time limited enabled.. ex throw on updatePaymentReference
* [RWC-251](https://tracker.tornevall.net/browse/RWC-251) - Orders with orderTotal of 0.
* [RWC-252](https://tracker.tornevall.net/browse/RWC-252) - Log on country change
* [RWC-254](https://tracker.tornevall.net/browse/RWC-254) - Ability to disable annuity factors
* [RWC-255](https://tracker.tornevall.net/browse/RWC-255) - Show supported payment method in annuity factors
* [RWC-256](https://tracker.tornevall.net/browse/RWC-256) - Use updateCheckoutOrderLines as safe layer during "
  desynched" cart
* [RWC-257](https://tracker.tornevall.net/browse/RWC-257) - Meta data view too big, make toggler
* [RWC-259](https://tracker.tornevall.net/browse/RWC-259) - Queuing callback status updates is the way of handling race
  conditions
* [RWC-260](https://tracker.tornevall.net/browse/RWC-260) - Handle order statuses from landingpage with queue
* [RWC-261](https://tracker.tornevall.net/browse/RWC-261) - Move getCustomerRealAddress to OrderHandler
* [RWC-262](https://tracker.tornevall.net/browse/RWC-262) - Move Helpers to Service
* [RWC-265](https://tracker.tornevall.net/browse/RWC-265) - Make callbacks handle problematic callbacks
* [RWC-267](https://tracker.tornevall.net/browse/RWC-267) - Removal of callbacks are not warning of "lost callbacks" in
  backend-admin
* [RWC-268](https://tracker.tornevall.net/browse/RWC-268) - WooTweaker: Ignore digest validations (on technical
  disturbances)
* [RWC-272](https://tracker.tornevall.net/browse/RWC-272) - Make sure saving credentials also updates callbacks properly
* [RWC-274](https://tracker.tornevall.net/browse/RWC-274) - Set up a better way for how we handled callback exceptions
* [RWC-275](https://tracker.tornevall.net/browse/RWC-275) - Priceinfo errorfixing
* [RWC-281](https://tracker.tornevall.net/browse/RWC-281) - RCO Checkout Error Handling
* [RWC-282](https://tracker.tornevall.net/browse/RWC-282) - Handle timeouts
* [RWC-283](https://tracker.tornevall.net/browse/RWC-283) - Cache annuity factors and payment methods so that they can
  work independently of Resurs health status
* [RWC-284](https://tracker.tornevall.net/browse/RWC-284) - Unreachable API's handling (AKA Christmas Holidays API
  Exception patch)
* [RWC-295](https://tracker.tornevall.net/browse/RWC-295) - Handle old plugin orders (but with ability to disable
  feature)

**Sub-task**

* [RWC-33](https://tracker.tornevall.net/browse/RWC-33) - woocommerce_resurs_bank_' . $type . '_checkout_icon (iconified
  method)
* [RWC-38](https://tracker.tornevall.net/browse/RWC-38) - resurs_trigger_test_callback
* [RWC-40](https://tracker.tornevall.net/browse/RWC-40) - resursbank_set_storeid
* [RWC-43](https://tracker.tornevall.net/browse/RWC-43) - resurs_getaddress_enabled
* [RWC-48](https://tracker.tornevall.net/browse/RWC-48) - resursbank_custom_annuity_string
* [RWC-52](https://tracker.tornevall.net/browse/RWC-52) - [#16](https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce/issues/16):
  resursbank_temporary_disable_checkout
