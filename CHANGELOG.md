Current ChangeLog is based on what has reached the master branch. Also, github references are now added.

# 0.0.1.0 + 1.0.0

** Epic
* [RWC-6](https://tracker.tornevall.net/browse/6) - RBWC 1.0

** Bugs noted
* [RWC-22](https://tracker.tornevall.net/browse/22) - CRITICAL Uncaught Error: Maximum function nesting level of '500' reached
* [RWC-97](https://tracker.tornevall.net/browse/97) - The blue box may not show properly when payments are not finished
* [RWC-171](https://tracker.tornevall.net/browse/171) - adminpage_details.phtml bugs out due to RCO.
* [RWC-198](https://tracker.tornevall.net/browse/198) - Simplified flow does not fill in country on getAddress
* [RWC-200](https://tracker.tornevall.net/browse/200) - [#7](https://github.com/Tornevall/wpwc-resurs/issues/7): simplified customerdata is not properly created
* [RWC-207](https://tracker.tornevall.net/browse/207) - The credential validation Button when updating credentials is not present.
* [RWC-223](https://tracker.tornevall.net/browse/223) - Weird behaviour in order process after delivery tests
* [RWC-229](https://tracker.tornevall.net/browse/229) - [#2](https://github.com/Tornevall/wpwc-resurs/issues/2)5: Password validation button disappeared
* [RWC-231](https://tracker.tornevall.net/browse/231) - Check if this is ours (Trying to get property 'total' of non-object in /usr/local/apache2/htdocs/ecommerceweb.se/woocommerce.ecommerceweb.se/wp-content/plugins/woocommerce/includes/abstracts/abstract-wc-payment-gateway.php on line 270)
* [RWC-232](https://tracker.tornevall.net/browse/232) - RCO positioning missing a title
* [RWC-233](https://tracker.tornevall.net/browse/233) - Saving data with credential validation
* [RWC-253](https://tracker.tornevall.net/browse/253) - Annuity factors with custom currency data
* [RWC-258](https://tracker.tornevall.net/browse/258) - ECom requesting payments four times in RCO mode

** Task
* [RWC-3](https://tracker.tornevall.net/browse/3) - composerize package PSR-4 formatted
* [RWC-7](https://tracker.tornevall.net/browse/7) - Basic administration Interface
* [RWC-12](https://tracker.tornevall.net/browse/12) - Generate README that explains architecture and other instructions.
* [RWC-13](https://tracker.tornevall.net/browse/13) - The plugin needs a basic structure
* [RWC-14](https://tracker.tornevall.net/browse/14) - Handle or abort deprecated actions and filters.
* [RWC-18](https://tracker.tornevall.net/browse/18) - Data::getTestMode() should be retreived from environment option
* [RWC-19](https://tracker.tornevall.net/browse/19) - Add getResursOption for deprecated plugin
* [RWC-20](https://tracker.tornevall.net/browse/20) - getDeveloperMode should be removed.
* [RWC-21](https://tracker.tornevall.net/browse/21) - Test plugin with < 3.4.0
* [RWC-24](https://tracker.tornevall.net/browse/24) - Establish ecom as API
* [RWC-25](https://tracker.tornevall.net/browse/25) - Is hasCredentials even in use anymore?
* [RWC-26](https://tracker.tornevall.net/browse/26) - Logging
* [RWC-28](https://tracker.tornevall.net/browse/28) - Test callback urls before using them (prod only)
* [RWC-29](https://tracker.tornevall.net/browse/29) - Storm-rearranged classes
* [RWC-42](https://tracker.tornevall.net/browse/42) - Multiple getaddress (ecom allows SE +NO)
* [RWC-57](https://tracker.tornevall.net/browse/57) - v3core-tracking
* [RWC-58](https://tracker.tornevall.net/browse/58) - Order view preparation (works as we have old data compatiblity present)
* [RWC-59](https://tracker.tornevall.net/browse/59) - Order view credentials
* [RWC-60](https://tracker.tornevall.net/browse/60) - Checkout: Simplified Shopflow
* [RWC-61](https://tracker.tornevall.net/browse/61) - Checkout: Hosted Flow
* [RWC-63](https://tracker.tornevall.net/browse/63) - [#1](https://github.com/Tornevall/wpwc-resurs/issues/1): Implement RCO legacy (postMsg)
* [RWC-64](https://tracker.tornevall.net/browse/64) - Checkout: Resurs Checkout (facelift) -- HappyFlow
* [RWC-65](https://tracker.tornevall.net/browse/65) - prepare fraud control flags with actions on bad selections
* [RWC-67](https://tracker.tornevall.net/browse/67) - Register callbacks
* [RWC-68](https://tracker.tornevall.net/browse/68) - RCO has its own terms inside iframe
* [RWC-69](https://tracker.tornevall.net/browse/69) - Show callback statuses in orderview instead of meta data
* [RWC-70](https://tracker.tornevall.net/browse/70) - Prepare simplified and methods
* [RWC-71](https://tracker.tornevall.net/browse/71) - Handle annuity factors
* [RWC-74](https://tracker.tornevall.net/browse/74) - isEnabled (option) should override active status for gateways
* [RWC-75](https://tracker.tornevall.net/browse/75) - Prevent rounding panic with too few decimals if possible
* [RWC-77](https://tracker.tornevall.net/browse/77) - signing marked should probably be a timestamp instead of a boolean
* [RWC-78](https://tracker.tornevall.net/browse/78) - Test coupons
* [RWC-79](https://tracker.tornevall.net/browse/79) - Add read more data and info for simplified+hosted.
* [RWC-80](https://tracker.tornevall.net/browse/80) - Test fraudcontrol in simplified
* [RWC-81](https://tracker.tornevall.net/browse/81) - Add constants for getCheckoutType instead of strings inside code.
* [RWC-82](https://tracker.tornevall.net/browse/82) - Make sure payment gateways are country based
* [RWC-98](https://tracker.tornevall.net/browse/98) - Change behaviour output of discount handling as the first part has been handled wrong
* [RWC-99](https://tracker.tornevall.net/browse/99) - Use native coupon description
* [RWC-102](https://tracker.tornevall.net/browse/102) - Show all hidden metadata in the box of Resurs information
* [RWC-103](https://tracker.tornevall.net/browse/103) - Ajax functions on API operation failures
* [RWC-105](https://tracker.tornevall.net/browse/105) - Support "instant finalizations"
* [RWC-107](https://tracker.tornevall.net/browse/107) - [#2](https://github.com/Tornevall/wpwc-resurs/issues/2)6: Unregister callbacks one by one
* [RWC-109](https://tracker.tornevall.net/browse/109) - Implement aftershop
* [RWC-110](https://tracker.tornevall.net/browse/110) - Logging of errors should not crash when $return is something else than expected in the flow selector
* [RWC-111](https://tracker.tornevall.net/browse/111) - Prevent interference with old orders and still allow old plugin handle old orders
* [RWC-113](https://tracker.tornevall.net/browse/113) - Add information about selected flow in user-agent
* [RWC-114](https://tracker.tornevall.net/browse/114) - govid should always be shown regardless of fields for getaddress
* [RWC-122](https://tracker.tornevall.net/browse/122) - Inherit government id from getAddress to resurs form fields
* [RWC-124](https://tracker.tornevall.net/browse/124) - Log getAddress events
* [RWC-128](https://tracker.tornevall.net/browse/128) - Custom translations for javascript/template sections
* [RWC-132](https://tracker.tornevall.net/browse/132) - Avoid locking company field as the chosen customer type as this field is not always updated in session
* [RWC-133](https://tracker.tornevall.net/browse/133) - Add a spinner to the getaddress button if not already there
* [RWC-136](https://tracker.tornevall.net/browse/136) - On field submission errors, make sure we translate which fields that is a problem
* [RWC-139](https://tracker.tornevall.net/browse/139) - Warn for Resurs Bank old gateway payments when old gateway is disabled
* [RWC-141](https://tracker.tornevall.net/browse/141) - Using getaddress should render setting country if exists. Country is also missing in customersync for RCO
* [RWC-145](https://tracker.tornevall.net/browse/145) - (Always validate on credential/environmental changes -- monitor updates) Switching between test and production does not validate accounts.
* [RWC-146](https://tracker.tornevall.net/browse/146) - Annuity factors for DK
* [RWC-148](https://tracker.tornevall.net/browse/148) - Clarify if card number for "befintligt kort" is mandatory
* [RWC-152](https://tracker.tornevall.net/browse/152) - Resurs Payment gateway country limitations
* [RWC-153](https://tracker.tornevall.net/browse/153) - Implement updatePaymentReference in RCO
* [RWC-158](https://tracker.tornevall.net/browse/158) - Track API history with metadata (?)
* [RWC-159](https://tracker.tornevall.net/browse/159) - Use filters to change min-max amount based on customizations
* [RWC-161](https://tracker.tornevall.net/browse/161) - Deprecated functions from ECom 1.3.59 and inspections
* [RWC-163](https://tracker.tornevall.net/browse/163) - Make sure the cart is always synchronizing in rco
* [RWC-166](https://tracker.tornevall.net/browse/166) - [#1](https://github.com/Tornevall/wpwc-resurs/issues/1)5: Checkout: Resurs Checkout (facelift) -- Payment failures
* [RWC-173](https://tracker.tornevall.net/browse/173) - Add proper extended logging to RCO sessions
* [RWC-174](https://tracker.tornevall.net/browse/174) - Is this really a proper value?
* [RWC-176](https://tracker.tornevall.net/browse/176) - Hide getAddress button on unsupported countries.
* [RWC-177](https://tracker.tornevall.net/browse/177) - When getAddress fields are not present
* [RWC-179](https://tracker.tornevall.net/browse/179) - Denied payment, change govId, try again (v2)
* [RWC-180](https://tracker.tornevall.net/browse/180) - [#2](https://github.com/Tornevall/wpwc-resurs/issues/2): Synchronize billing address with getPayment
* [RWC-182](https://tracker.tornevall.net/browse/182) - Activate script enqueue for RCO only if there is a cart
* [RWC-183](https://tracker.tornevall.net/browse/183) - [#1](https://github.com/Tornevall/wpwc-resurs/issues/1)5: Checkout: Resurs Checkout PaymentFail (Legacy)
* [RWC-184](https://tracker.tornevall.net/browse/184) - [#3](https://github.com/Tornevall/wpwc-resurs/issues/3): Resurs Checkout: Store and use payment method on purchase
* [RWC-185](https://tracker.tornevall.net/browse/185) - Resurs Checkout Handle failures (signing=>mockfail) -- FailUrl Redirect
* [RWC-186](https://tracker.tornevall.net/browse/186) - [#4](https://github.com/Tornevall/wpwc-resurs/issues/4): RCOv2 Resurs Checkout: Store and use payment method on purchase
* [RWC-187](https://tracker.tornevall.net/browse/187) - Make sure we validate AES methods BEFORE using them in wc-api
* [RWC-189](https://tracker.tornevall.net/browse/189) - [#1](https://github.com/Tornevall/wpwc-resurs/issues/1)1: setOrderMeta after RCO session should include paymentMethodInformation
* [RWC-190](https://tracker.tornevall.net/browse/190) - Docs only
* [RWC-191](https://tracker.tornevall.net/browse/191) - [#5](https://github.com/Tornevall/wpwc-resurs/issues/5): Update initial translations explicitly created during RCO
* [RWC-195](https://tracker.tornevall.net/browse/195) - [#1](https://github.com/Tornevall/wpwc-resurs/issues/1)7: setStoreId filter should not be an integer (prepare for future api's)
* [RWC-196](https://tracker.tornevall.net/browse/196) - do_action at resurs statuses and callbacks
* [RWC-199](https://tracker.tornevall.net/browse/199) - [#6](https://github.com/Tornevall/wpwc-resurs/issues/6): setOrderMeta should have an insert function
* [RWC-201](https://tracker.tornevall.net/browse/201) - Facelift: Make sure that payment method is updated, if clicked twice (during denied at first)
* [RWC-202](https://tracker.tornevall.net/browse/202) - Store last registered callback url locally so that we can see if the urls need to be reupdated
* [RWC-203](https://tracker.tornevall.net/browse/203) - On admin main front where credentials are set make sure data will be resynched on save
* [RWC-204](https://tracker.tornevall.net/browse/204) - Test what happens if checkout type is switched in middle of a payment
* [RWC-208](https://tracker.tornevall.net/browse/208) - When credentials are saved, make sure callbacks are resynched in background
* [RWC-209](https://tracker.tornevall.net/browse/209) - Monitor saved data to update methods and callbacks on credendial updates
* [RWC-210](https://tracker.tornevall.net/browse/210) - price variations?
* [RWC-214](https://tracker.tornevall.net/browse/214) - [#1](https://github.com/Tornevall/wpwc-resurs/issues/1)3, #8: Refuse to set a status that is already set.
* [RWC-215](https://tracker.tornevall.net/browse/215) - [#9](https://github.com/Tornevall/wpwc-resurs/issues/9): Necessary callbacks, remove the rest (if not already removed).
* [RWC-216](https://tracker.tornevall.net/browse/216) - updatePaymentReference and exceptions +logging when it happens
* [RWC-217](https://tracker.tornevall.net/browse/217) - forceSigning is deprecated.
* [RWC-219](https://tracker.tornevall.net/browse/219) - [#1](https://github.com/Tornevall/wpwc-resurs/issues/1)0: According to how RCO works in the docs we probably should change canProcessOrder to avoid conflicts in the payment flow
* [RWC-220](https://tracker.tornevall.net/browse/220) - [#1](https://github.com/Tornevall/wpwc-resurs/issues/1)3: Refuse to set a status that is already set in synchronous mode
* [RWC-221](https://tracker.tornevall.net/browse/221) - [#1](https://github.com/Tornevall/wpwc-resurs/issues/1)4: During getMetaData-requests, make it possible to fetch getPaymentinfo
* [RWC-224](https://tracker.tornevall.net/browse/224) - Errors caused by woocommerce thrown to setRbwcGenericError gets double div's for .woocommerce-error
* [RWC-225](https://tracker.tornevall.net/browse/225) - When activating other delivery address make sure to match the addressrow, to avoid weird addressing
* [RWC-226](https://tracker.tornevall.net/browse/226) - Make sure that the customer session is really killed after success
* [RWC-227](https://tracker.tornevall.net/browse/227) - nonces for background processing in wp-admin
* [RWC-228](https://tracker.tornevall.net/browse/228) - [#2](https://github.com/Tornevall/wpwc-resurs/issues/2)3: Credential validations by ajax must not activate getOptionsControl.
* [RWC-230](https://tracker.tornevall.net/browse/230) - Make sure we synchronize order after successful orders with getPayment
* [RWC-234](https://tracker.tornevall.net/browse/234) - Add error report note when changing credentials and the credentials is failing
* [RWC-236](https://tracker.tornevall.net/browse/236) - annuityfactors - read more link restoration
* [RWC-237](https://tracker.tornevall.net/browse/237) - $order->status_update() should be cast into notes that can be identified as the plugin.
* [RWC-241](https://tracker.tornevall.net/browse/241) - Validate button for credentials fix
* [RWC-242](https://tracker.tornevall.net/browse/242) - Special feature: getAddress resolve non mocked data
* [RWC-243](https://tracker.tornevall.net/browse/243) - Move to getDependentSettings plugin-file (This is filter based)
* [RWC-244](https://tracker.tornevall.net/browse/244) - Admin filter tweak: Override country setting
* [RWC-245](https://tracker.tornevall.net/browse/245) - Tweak for note prefix might not work properly
* [RWC-246](https://tracker.tornevall.net/browse/246) - Log frontent-to-backend
* [RWC-247](https://tracker.tornevall.net/browse/247) - mock mode (fake error in test by intentionally throw errors by config - - time limited enabled.. ex throw on updatePaymentReference
* [RWC-251](https://tracker.tornevall.net/browse/251) - Orders with orderTotal of 0.
* [RWC-252](https://tracker.tornevall.net/browse/252) - Log on country change
* [RWC-254](https://tracker.tornevall.net/browse/254) - Ability to disable annuity factors
* [RWC-255](https://tracker.tornevall.net/browse/255) - Show supported payment method in annuity factors
* [RWC-256](https://tracker.tornevall.net/browse/256) - Use updateCheckoutOrderLines as safe layer during "desynched" cart
* [RWC-257](https://tracker.tornevall.net/browse/257) - Meta data view too big, make toggler
* [RWC-259](https://tracker.tornevall.net/browse/259) - Queuing callback status updates is the way of handling race conditions
* [RWC-260](https://tracker.tornevall.net/browse/260) - Handle order statuses from landingpage with queue
* [RWC-261](https://tracker.tornevall.net/browse/261) - Move getCustomerRealAddress to OrderHandler
* [RWC-262](https://tracker.tornevall.net/browse/262) - Move Helpers to Service
* [RWC-265](https://tracker.tornevall.net/browse/265) - Make callbacks handle problematic callbacks
* [RWC-267](https://tracker.tornevall.net/browse/267) - Removal of callbacks are not warning of "lost callbacks" in backend-admin
* [RWC-268](https://tracker.tornevall.net/browse/268) - WooTweaker: Ignore digest validations (on technical disturbances)
* [RWC-272](https://tracker.tornevall.net/browse/272) - Make sure saving credentials also updates callbacks properly
* [RWC-274](https://tracker.tornevall.net/browse/274) - Set up a better way for how we handled callback exceptions
* [RWC-275](https://tracker.tornevall.net/browse/275) - Priceinfo errorfixing
* [RWC-281](https://tracker.tornevall.net/browse/281) - RCO Checkout Error Handling
* [RWC-282](https://tracker.tornevall.net/browse/282) - Handle timeouts
* [RWC-283](https://tracker.tornevall.net/browse/283) - Cache annuity factors and payment methods so that they can work independently of Resurs health status
* [RWC-284](https://tracker.tornevall.net/browse/284) - Unreachable API's handling (AKA Christmas Holidays API Exception patch)
* [RWC-295](https://tracker.tornevall.net/browse/295) - Handle old plugin orders (but with ability to disable feature)

** Sub-task
* [RWC-33](https://tracker.tornevall.net/browse/33) - woocommerce_resurs_bank_' . $type . '_checkout_icon (iconified method)
* [RWC-38](https://tracker.tornevall.net/browse/38) - resurs_trigger_test_callback
* [RWC-40](https://tracker.tornevall.net/browse/40) - resursbank_set_storeid
* [RWC-43](https://tracker.tornevall.net/browse/43) - resurs_getaddress_enabled
* [RWC-48](https://tracker.tornevall.net/browse/48) - resursbank_custom_annuity_string
* [RWC-52](https://tracker.tornevall.net/browse/52) - [#1](https://github.com/Tornevall/wpwc-resurs/issues/1)6: resursbank_temporary_disable_checkout

