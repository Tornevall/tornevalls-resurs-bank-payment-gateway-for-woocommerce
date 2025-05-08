# 1.2.12

WOO-1452 Period is reset each time config enters get-store-country

# 1.2.11

* WOO-1454 Critical akamai hotfix, enforcing ecom to resolve on IPv4.

# 1.2.10

Readme update for WP 6.8.

# 1.2.7 - 1.2.9

* WOO-1439 Fix PHP warnings and notices in relation to resolving WC information
* No changes in the plugin, but in the ecom library for where the part payment information has been further compacted.

# 1.2.6

* WOO-1434 Switching between stores may cause payment method desynch

# 1.2.5

* WOO-1433 Performance issues in cart and checkout

# 1.2.4

* Finnish translation hotfix.

# 1.2.3

* WOO-1428 Instead of doing all country checks in the plugin, we should take advantage of Location that was unreachable from Config::setup before
* WOO-1422 Performance issues and bugs for costlist
* WOO-1429 Changing stores does not necessarily mean we're clearing the entire cache
* WOO-1431 Running Resurs plugin with WooCommerce disabled.
* WOO-1432 Update ecom to show necessary values in cost-list

# 1.2.2

* WOO-1426 Unsupported themes makes widgets go nuts \(sometimes\)
* WOO-1427 Country checks required in react

# 1.2.0 - 1.2.1

* WOO-1418 Implement breaking changes for Part Payment widget
* WOO-1420 Implementation of New Part Payment Widget and Warning Widget in Checkout – Compliance with New Legal Requirements
* WOO-1423 \\Resursbank\\Woocommerce\\Modules\\PartPayment\\PartPayment::getWidget
* WOO-1424 Restructure css/js for legal requirements to not execute under partpayment scripthooks
* WOO-1425 \\Resursbank\\Woocommerce\\Modules\\PartPayment\\PartPayment::setCss

# 1.1.5

* WOO-1417 ppw period resets to wrong value

# 1.1.1 - 1.1.4

* WOO-1411 About-widget broken
* WOO-1413 Some stores, during upgrade, may get JWT errors
* WOO-1415 Remove \(if possible\) extra sort order on blocks methods
* WOO-1416 slow loading with get-address?
* WOO-1414 isEnabled shouts false positives
* WOO-1413 Some stores, during upgrade, may get JWT errors
* Uncatched blocks exception handled.

# 1.1.0

* WOO-1373 Update src/Modules/GetAddress/resources/update-address/legacy.js
* WOO-1379 Confirm functionality of logged in customer
* WOO-1384 wp-admin payment method editor says incompatible methods
* WOO-1403 New url to docs in readme
* WOO-1407 Investigation of Support for Payment Method Management and Sorting in WooCommerce Blocks
* WOO-1396 Missing company payment method
* WOO-1397 Legacy checkout do not reload payment methods
* WOO-1400 Purchase button invalidates in specific occasions for some LEGAL method
* WOO-1402 Billing address are not seind in deliveryAddress with blocks
* WOO-1404 Error message from Merchant-api is missing
* WOO-1405 For a Finnish account, the threshold value should be 15€
* WOO-1406 The Legacy checkout does not list the "correct" payment methods at checkout depending on the country
* WOO-1409 Email is not properly added to payload when order are created
* WOO-1410 Send the personal identification number, email, and mobile number of the person responsible for the payment, i.e., the details required by the service provider.
* WOO-1412 PPW not showing after resetting values
* WOO-1378 Test Blocks and Legacy

# 1.0.58

WOO-1390 Partpayment widget errors with out of stocks

# 1.0.57

* Hotfix for missing files in the WP repo.

# 1.0.56

* WOO-1246 Resolve default store id by using ECom method instead
* WOO-1359 Remove getSiteLanguage and \\Resursbank\\Woocommerce\\Database\\Options\\Api\\StoreCountryCode
* WOO-1366 1359/1246: Clean up Language/Country/Default Store transient handling.
* WOO-1364 Error in ecom.log when fetchAddress in checkout
* WOO-1381 Missing type check in Modules\\Gateway\\Gateway::modifyIcon

# 1.0.50 - 1.0.55

* Miscellaneous hotfixes.
* WOO-1355 PPW renders duplicate payment \(and too many\) payment methods
* WOO-1353 Memory exhaustion patch.

# 1.0.49

* WOO-1343 Adjust for ECP-855 changes
* WOO-1345 Apply ECP-860 changes
* WOO-1351 Unable to handle payments not belonging to Resurs in special circumstances

# 1.0.43 - 1.0.48

* Hotfixes for various problems. One covers a "live" vs "coming soon"-pages issue. Another one is based on a misplaced script-tag.

# 1.0.42

* WOO-1308 Upgrade to v3
* WOO-1332 getAddress don't show up
* WOO-1333 get use of rb-ga-gov-id to fill in company name on LEGAL
* WOO-1337 Raise woocommerce too ecom v3-tag
* WOO-1329 Fix annuity-factor widget in admin \(Breaking change for v3\).
* WOO-1334 createPaymentRequest classes moved to models
* WOO-1335 PPW: Period laddas inte vid val av betalmetod
* WOO-1336 Admin: Butiksval laddas inte in vi byte av credentials
* WOO-1338 Government id not submitted to createpayment
* WOO-1339 Modify seems to not work properly
* WOO-1340 Bulk finalization may still cause booleans instead of null when using getOrder
* WOO-1342 About-widget errors

# 1.0.41

* WOO-1330 Continued.

# 1.0.40

* WOO-1330 Legacy post id's not null, but false, in exclusive occasions

# 1.0.39

* WOO-1312 Required: High Performance Order Storage
* WOO-1327 Make icons/logotypes float right

# 1.0.38

getPayment MAPI overload Hotfix.

# 1.0.37

* WOO-1324 Investigate and Possibly Remove getPriceSignage false errors in the Log
* WOO-1322 Error Occurs When Attempting to Charge a Frozen Order via Bulk Update in the Order List
* WOO-1323 Prevent race conditions between "thankyou" and callbacks after order creation-retries
* WOO-1326 canCapture in orderlist still a performance issue

# 1.0.36

* WOO-1317 Handle Orders Marked as Rejected and Older Than 30 Days
* WOO-1319 Check for hardcoded vatrates
* WOO-1315 Order list crashes when checking statuses with capturable orders
* WOO-1318 Unable to Change Order Status Even When Order Management Against Resurs is Deactivated
* WOO-1321 Disabled modify may cause wrong captures

# 1.0.35

* WOO-1315 Order list crashes when checking statuses with capturable orders

# 1.0.34

* WOO-1313 Programmatic order updates will cancel FROZEN payments

# 1.0.33

Reverted the failed-status setup from previous release.

# 1.0.32

* WOO-1310 Frozen\+Rejected orders are cancelled twice

# 1.0.31

* WOO-1306 Payment methods by country limit

# 1.0.29 - 1.0.30

* Issues eventually discovered with composer.json

# 1.0.28

* Translation hotfix.

# 1.0.27 (ECom2-2.x)

* WOO-1291 Add plugin and platform information to the metadata
* WOO-1293 Verification of woo with ECP-636
* WOO-1295 Complete credit-denied
* WOO-1299 Add an X-close-modal at payment method level in checkout area
* WOO-1302 Implement revised changes from Payment Information module \(ecom2-v2\)
* WOO-1305 Release of single patch from  ECP-745 \(finnish translation for partpay string\)
* WOO-1294 Ecom2 v2.0 locale/language changes
* WOO-1297 Testing callbacks fails
* WOO-1298 Layout issues in cost info
* WOO-1300 Annuity after reload issues
* WOO-1304 Read more/Priceinfovy i kassa: Unable to fetch product, #0 \[...\]PartPayment.php\(83\)

# 1.0.24 - 1.0.26

* WOO-1305 Finnish translation for part payment info.

# 1.0.23

* Only tag change.

# 1.0.22

* WOO-1292 Norska basöversättningar för woocommerce

# 1.0.21

* WOO-1290 Mobile view for priceinfo may be broken depending on the width

# 1.0.20

* WOO-1289 Payment information widget should have z-index 999999 \(linked issue for deploy in Woocommerce\)

# 1.0.19

* WOO-1288 Partpayment infotext \(norwegian\)

# 1.0.18

* WOO-1285 Typed static property Resursbank\\Ecom\\Config::$instance must not be accessed before initialization
* WOO-1283 locale_rework

# 1.0.17

* WOO-1283 Findings: fi/no translations for checkout

# 1.0.16

* WOO-1282 Disabla getAddress-wiget om vald butik <> "countryCode": "SE"

# 1.0.15

* WOO-1279 Verify the plugin with rcoplus branch and WooCommerce 8.5.0
* WOO-1280 Undefined array key warnings in checkout/wp-admin

# 1.0.14

* Rebuilt commit.

# 1.0.13

* WOO-1274 Handle gateway sort order in checkout based on wp-admin setup
* WOO-1267 Make "tested up to 8.0.x" work properly.
* WOO-1276 Read more CSS malfunction on custom themes

# 1.0.12

* WOO-1275 Variable products part payment script errors

# 1.0.11

* WOO-1271 /price\_signage?amount=0" tiggas 1000 ggr per dag
* WOO-1272 Regler för paymentwidget
* WOO-1273 Switching between customer types may confuse the platform customertype

# 1.0.10

* WOO-1270 Enforce input field for companies \(company id\)

# 1.0.9

* WOO-1264 Change behaviour of how payment gateways/methods are displayed in admin and checkout pages
* WOO-1265 Redirect from "payments" no longer works
* WOO-1261 get\_query\_var: Call to a member function get\(\) on null
* WOO-1266 Prevent min/max, etc to not show warnings when gateway methods is not present
* WOO-1268 Partpayment admin widget conflicts \(0059993\)

# 1.0.8

* WOO-1261 get\_query\_var: Call to a member function get\(\) on null

# 1.0.7

* WOO-1260 Fix inaccurate return type in \\Resursbank\\Woocommerce\\Modules\\Order\\Status::orderStatusFromPaymentStatus

# 1.0.6

* WOO-1249 Timeouts in the store may block site access completely if misconfigured

# 1.0.5 (ECom Upgrade)

* WOO-1257 USP strings with wrong notices when translations are missing

# 1.0.4 (ECom Upgrade)

* WOO-1252 Changed description length from 50 till 100 in ecom package
* WOO-1250 Extend logging on getStores errors / Troubleshooting getStores and TLS \(?\)
* WOO-1253 Error: Failed to obtain store selection box \(ecom-related\)
* WOO-1254 Msgbox at Resurs settings
* WOO-1255 Store fetcher does not work

# 1.0.3

* WOO-1250 Extend logging on getStores errors

# 1.0.2

* WOO-1248 Unable to switch to production

# 1.0.0 - 1.0.1

* WOO-547 Replace method name in confirmation message on screen if possible
* WOO-640 Konflikter: Språkdomänen behöver ändras \(tornevall-resursbank-gateway-xxx\) till korrekt
* WOO-641 Konflikter: Slug och readme behöver anpassas till Resurs
* WOO-687 Defaultinstallationer där decimalerna blir 0
* WOO-705 Implementera MAPI config: Client/Secret
* WOO-706 Implementera MAPI/getAddress
* WOO-707 Initiera konfiguration för ecom2 \(bundla i gamla modulen går ej\)
* WOO-708 Hantera stores is wp-admin
* WOO-710 3.0 - Implementera getPayment \(återupptagen för lagning\)
* WOO-712 3.0 - Hantera betalmetoder centralt i samma gränssnitt som tidigare
* WOO-715 Hur vet vi vilket API som används? \(Alla anrop\)
* WOO-716 createPayment \(main task\), kommer segmenteras \(men tid bör rapporteras här\)
* WOO-718 Betalmetoder i wp-admin
* WOO-721 3.0 - Alla spår av MAPI \(MerchantAPI\) i huvudpluginet ska bort
* WOO-722 3.0 - Stäng av SOAP för getPaymentMethods när MAPI är aktivt.
* WOO-724 3.0 - Uppgradera server för ecom2... \(\+Installera plugin på WC4\)
* WOO-729 MAPI: Översättning till svenska
* WOO-730 Migrera MAPI-credentials från SOAP-settings \(Samt passa på att ta bort MAPI-fliken från admin\)
* WOO-731 MAPI getPaymentMethods behöver automatiseras
* WOO-733 Se till att WordPress inte slår ned på detta i sin plugin-review när vi submittar det
* WOO-736 USP vid betalmetoder i kassan
* WOO-738 MAPI - Read more / "part pay from" / annuity-widgets
* WOO-739 MAPI - Behöver en större paymentMethods-modell sparad för MAPI i getPaymentMethods-cachen i den mån det krävs ytterligare info vid köp för att köp ska kunna göras
* WOO-742 Implementera en ecom2-baserad getPayment-ruta.
* WOO-743 Migrera in MAPI-funktionaliteten till mainpluginet, ställ upp PHP-requirements till 8.1 \(Delleverans\)
* WOO-744 Byt namn på canLog så att loggningsfunktionaliteten alltid avgör om loggning kan göras "längre upp"
* WOO-746 Ta bort RCO-stöd
* WOO-747 Lägg på bundlade krav för ecom2
* WOO-749 Flytta in MAPI så att det blir en enhetlig del med nuvarande gateway \(ResursDefault\)
* WOO-750 Avveckla processHosted som inte kommer kunna användas med MAPI
* WOO-753 MAPI - Avveckla reggning/visning av SOAP-callbacks \(Den stör wp-admin i och med att SOAP försvinner\)
* WOO-755 Validera att konto fungerar med jwt-getStores i stället för soapvarianten
* WOO-757 Stores-fetcher now work, but we still need to make sure that it works when data is bad or empty.
* WOO-762 Verifiera getAddress efter import av modul
* WOO-763 Verifiera att kontokonverteringen från v2.2 inte har fått bytt "Login"-datat när vi bytte namn till jwt
* WOO-775 När storeslistan genereras första gången väljs första storeid som dyker upp i listan
* WOO-780 Disable setpreferredflow as it does not exist in ecom2
* WOO-781 Disable "the three flags" as they work differently in ecom2
* WOO-790 Log options should be replaced by a single option to specify logpath, when empty use None in Ecom, otherwise Filesystem with speicifed path
* WOO-791 Hantera Data::loggern.
* WOO-792 Implement payment method list
* WOO-806 Fix error handling in src/Settings/Api.php :: getJwt\(\)
* WOO-807 quantityUnit har använt ecom1 tidigare
* WOO-814 metadata för MAPI externalCustomerId \(update: must be nullable\)
* WOO-816 Use transient as "cache" \(for paymentmethods\)
* WOO-822 Få wp-admin att funka med betalmetoder igen.
* WOO-823 MAPI-Create: Options & Customer \(Slutförande av första delen i createn för att skapa order på "båda sidorna"\)
* WOO-824 Avveckla RCO helt och invänta RCO\+
* WOO-829 Rätta till ecom2-loggningen så att instansen alltid finns närvarande
* WOO-832 Save storeId differently during render, when only one store is available in getStores
* WOO-839 Transfer translation to ecom2 \(Vänligen välj butik\)
* WOO-841 Hantera loggning på loglevels i ecom
* WOO-842 Update locales \(ECP-251\)
* WOO-844 getDefaults från getData-värden
* WOO-854 Use !Config::isProduction for this check.
* WOO-856 Plocka bort  ecom1-beroendet
* WOO-857 MAPI-Create: Callbacks och urler
* WOO-860 Get adress
* WOO-867 qa
* WOO-868 Centralize callback handling
* WOO-869 Städa upp gamla getAddress-fragment
* WOO-871 getAddress uppdaterar inte company name vid customerType=legal
* WOO-874 Apply content fitlering for Get Address widget \(HTMl sanitizing\)
* WOO-877 Part payment: use \\Resursbank\\Ecom\\Module\\PriceSignage\\Repository::getPriceSignage :: getPriceSignage\(\) to resolve part payment price
* WOO-883 Använd Resurs validering av inputfält i checkout \(phone, osv\).
* WOO-884 Init ecom vs Route::exec
* WOO-889 Fraktkostnad kommer inte med i order till resurs
* WOO-898 Utred responsecontrollern för callbacks
* WOO-899 Add additional PHPCS rules
* WOO-900 felaktigheter i create /payments
* WOO-904 callbackHandler getPayment Final
* WOO-905 Innehåll i orderLines
* WOO-906 contactperson på NATURAL
* WOO-908 Create converter for order objects \(like cart converter at checkout\)
* WOO-909 Cart converter
* WOO-913 PPW - config limit
* WOO-914 Fixa successpage på samma sätt som orderstatus sätts i callbacks
* WOO-924 Ta bort application-blocket från createn igen
* WOO-932 Interna redirects från flowui till ecompress tar väldigt lång tid.
* WOO-935 Felaktiga uppgifter i createn som orsakar trace-exceptions, gör att vi i vissa fall tappar info om vad som gått fel. Kan vi göra detta bättre?
* WOO-936 Namn på betalmetoden isf id
* WOO-938 Write end-user documentation for PPW
* WOO-939 Use CartConverter to fetch OrderLineCollection
* WOO-941 Customer landing success-översättning \(ecom\)
* WOO-948 getProperGatewayId vs rad 460 \(get\_title\)
* WOO-949 Kan vi validera $screen bättre?
* WOO-950 All auto-adjustments at once
* WOO-952 Use Language instead of Locale
* WOO-955 Bitbucket/Dashboard \(git:22\) Felsökning
* WOO-962 Rename resursbank\_order\_reference in meta to resursbank\_payment\_id
* WOO-1001 phpcbf run on ResursDefault
* WOO-970 Order Management - som handlare vill jag kunna hantera orders i wp-admin
* WOO-971 Company Shop Flow - som företag vill jag kunna handla med Resurs
* WOO-994 PPW - som konsument vill jag se månadskostnad vid kredit hos Resurs
* WOO-995 Private Shop Flow - som privatkonsument vill jag kunna handla med Resurs
* WOO-1064 Jag vill kunna hantera Legacy orders med nya mapi-plugin
* WOO-991 Implementera Resurs merchant API
* WOO-798 Delete cache dir option related files
* WOO-799 Validate wsrc/Database/Options/Environment.php against enum in Ecom
* WOO-800 Add validation of cache director src/Database/Options/LogDir.php before set:er is executed to ensure you gave me a writable directory
* WOO-802 Translations under src/wp-content/plugins/resursbank-woocommerce/src/Settings/\* should be moved to Ecom
* WOO-804 We need Exception handling in src/Settings/PaymentMethods.php :: getOutput
* WOO-805 Use ECom for credential valiation in src/Settings/Api.php
* WOO-826 preProcess-payment and order id handling \(apidata is no longer necessary\)
* WOO-827 Add setting to specify logg verbosity
* WOO-828 Implement wp-admin functionality for clearing ecom cache
* WOO-830 Supportinfo i admin
* WOO-847 Add support for transient cache, using cache interface from ECom
* WOO-858 Complete callback implementation
* WOO-859 In autoloader.php, remove the segment supporting ResursBank namespace
* WOO-872 Refactor src/wp-content/plugins/resursbank-woocommerce/src/Settings/Advanced.php :: getLogger\(\)
* WOO-873 Refactor \\Resursbank\\Woocommerce\\Settings::output
* WOO-880 Pass the output for our part payment widget HTML through a filter
* WOO-893 Refactor \\Resursbank\\Woocommerce\\Util\\Database::getOrderByPaymentId
* WOO-894 Refactor \\Resursbank\\Woocommerce\\Settings\\Api::getStoreSelector
* WOO-895 Refactor \\Resursbank\\Woocommerce\\Modules\\GetAddress\\Filter\\Checkout::register
* WOO-896 Refactor \\Resursbank\\Woocommerce\\Settings\\Api::getSettings
* WOO-897 Refactor \\Resursbank\\Woocommerce\\Settings\\Advanced::getSettings
* WOO-921 Correct execution of composer binary in qa/setup
* WOO-944 Refaktorera metadata för customerId
* WOO-945 Refactor getDeliveryFrom
* WOO-947 Updates to pre-commit script, ensuring all QA tools are executed
* WOO-953 src/Util/Url request methods refactor
* WOO-956 Download latest version of composer in qa/setup script
* WOO-963 Implement support for canceling orders in wp-admin
* WOO-964 Implement support for refunding \(both full and partial\) through wp-admin
* WOO-965 Implement capture support through wp-admin
* WOO-978 Refactor \\Resursbank\\Woocommerce\\Modules\\PartPayment\\Module::getWidget & setCss, reduce complexity
* WOO-979 Refactor \\Resursbank\\Woocommerce\\Settings\\PartPayment::getSettings method is too large
* WOO-981 Refactor \\Resursbank\\Woocommerce\\Settings\\PartPayment::getPaymentMethods & getAnnuityPeriods
* WOO-982 Remove \\Resursbank\\Woocommerce\\Util\\Url::getSanitizedArray
* WOO-983 Intoruce QA updates from ECom. Fix reported problems.
* WOO-987 Resurs elements displayed on order view for unrelated order
* WOO-988 Remove PHPCBF temporarily
* WOO-989 Enable PHPCBF again
* WOO-990 getResursOption bort
* WOO-998 WP-modulen är beroende av php8.1-intl.
* WOO-999 QA - phpcbf must check if files exist before executing
* WOO-1000 Plocka bort legacy-skräp i ResursDefault
* WOO-1005 Fix phpcs pathing
* WOO-1007 userAgent från wc-plugin till ecom\+
* WOO-1008 Part payment settings does not indicate it's updating period options when you change payment method
* WOO-1014 Move \\Resursbank\\Woocommerce\\Util\\Database::getOrderByPaymentId to Metdata class
* WOO-1016 Inställning för att slå på / av order management, \(Capture,Cancel,Refund\)
* WOO-1018 Correct usage of let / const in javascript
* WOO-1020 Adjust WooCommerce module to include ECP-379 fixes
* WOO-1021 När credentials konfigureras första gången
* WOO-1025 Korrigera order converter för korrekt pris på rabatter
* WOO-1030 Case mismatch in configuration option titles
* WOO-1032 Genomgång av statushantering \(hantering av on-hold saknas\)
* WOO-1034 Statushantering: Skillnad mellan nekad kredit och fallerad sign/auth
* WOO-1035 OrderLines should always use SKU
* WOO-1037 Controllers should not throw, since WooCom / WP will not handle Throwable in AJAX calls, and likely not in any requests at all, which means sensitive information can become exposed
* WOO-1038 On Payment Methods tab we should either hide the Save button, or we should at least put a margin below our table so it looks better
* WOO-1039 Modify order by adding the supplied order lines
* WOO-1041 Add translation helper
* WOO-1042 Refactor src/Modules/Order/Filter/DeleteItem::isShipping
* WOO-1043 Flytta "Advanced settings"-fliken till längst till höger
* WOO-1045 Test logging, ensure Util\\Log methods work as expected when invoked
* WOO-1050 Flytta \(ta bort\) trace från inkomna callback-noteringar och lägg den endast i loggfiler
* WOO-1051 Vid debitering \(capture\) skapa en notis på aktuell order
* WOO-1054 \\Resursbank\\Woocommerce\\Database\\Options All require PhpMissingParentCallCommonInspection to be annotated, should be fixed in Inspections
* WOO-1055 Implementera priceSignagePossible från /payment\_methods
* WOO-1056 Vid annullering \(cancel\) skapa en notis på aktuell order
* WOO-1057 Centralisering av amount med formaterad currency
* WOO-1060 \[Docs\] We require SKU
* WOO-1061 We fetch our payment methods several times on each pageload in the admin panel
* WOO-1063 init.php executes 5 times on a single page request \(order view\).
* WOO-1065 Säkerställa hantering av legacy orders
* WOO-1067 Centralisering av getFormattedAmount genom exvis Util\\Currency
* WOO-1068 Refaktorera cancel-metoden
* WOO-1069 Centralisering av notis med summering
* WOO-1073 Check if we should use $\_GET or $\_POST for callback requests
* WOO-1079 Centralize code in OrderManagement classes
* WOO-1084 Check all add\_action and add\_filter should only use strings, not anonymous functions
* WOO-1085 ModuleInit module, and re-structure of module base classes
* WOO-1086 Inspection corrections after order management releases
* WOO-1095 Review order notes during checkout and after-shop process and refine it
* WOO-1096 When you initially refund an order the \\Resursbank\\Woocommerce\\Modules\\Ordermanagement\\Refunded::performRefund executes, but not when changing statuses back and forth
* WOO-1103 Rename Messagebag method parameter 'msg' to 'message'
* WOO-1105 Payment is sometimes called order
* WOO-1109 Cleanup dprecated code
* WOO-1110 Confirm contents of LICENSE file
* WOO-1111 Filtering contents of \\Resursbank\\Woocommerce\\Modules\\UniqueSellingPoint\\Module::setCss
* WOO-1112 Visa bara notering om att även göra refund hos payment gateway om setting för refund är aktiverad
* WOO-1113 Förtydliga översättning från engelska till svenska
* WOO-1114 Custom fields på ordervyn, vilka är nödvändiga?
* WOO-1116 Move validateLimit from add\_action call in WordPress.php to Options\\PartPayment\\Limit::setData
* WOO-1117 Review size of payment method logotypes
* WOO-1118 Clear cache when you change environment / api username / pw
* WOO-1119 \[Test\] What happens if we place an order, remove the payment method from our account, then try to view the order?
* WOO-1122 Replace all implementations of wc\_get\_order with \\Resursbank\\Woocommerce\\Modules\\Ordermanagement\\Ordermanagement::getOrder
* WOO-1126 \\Resursbank\\Woocommerce\\Modules\\MessageBag\\MessageBag::add improvements
* WOO-1128 Applicera test-callback för testa kommunikation mot Resurs
* WOO-1129 Order Management settings should be enabled by default
* WOO-1133 \[Discuss\] When creating a partial refund, which fails, we no longer stop code execution
* WOO-1134 \[Discuss\] Adding additional information about why a payment action fails
* WOO-1135 \[Discuss\] Handling outcome of a payment being cancelled, but not updated, when modifying it
* WOO-1137 Add link to gateway in error messages from Order Management actions
* WOO-1139 Lägg in nya modulen på woocommerce3-servern
* WOO-1141 \[Documentation\] Make it clear that we recommend leaving the cache enabled.
* WOO-1142 \[Doks\] After shop functionality
* WOO-1143 Add support for fees
* WOO-1144 Improved multi-ship support
* WOO-1146 Fix part payment widget so that it doesn't break because of exceptions
* WOO-1147 Fix GetAddress widget handling of controller exceptions
* WOO-1150 src/Util/Route :: redirectBack improvements
* WOO-1151 Remove the handled sum from Order Management calls.
* WOO-1157 resursbank-woocommerce/js/resursbank\_partpayment.js should be moved to Partpayment module directory
* WOO-1158 A lot of hooks miss checks whether our module is enabled
* WOO-1160 Lägg till hantering av Management callback "modify\_order"
* WOO-1162 Testa plugin med "ett annat" tema
* WOO-1165 Lägg till plugin i "WP Repository"
* WOO-1166 Get address - enabled by default
* WOO-1170 Töm cache för betalmetoder när man går in på tab för betalmetoder
* WOO-1171 Clear all cache whenever we update settings
* WOO-1172 \[Docs\] When you change something with a payment method at Resurs Bank, or remove it, you should clear cache in WooCom
* WOO-1173 Reflect reason for failed purchase when you reach failUrl
* WOO-1175 Duplicate wrapper to extract WC\_Order \\Resursbank\\Woocommerce\\Modules\\Order\\Filter\\ThankYou::exec
* WOO-1176 \\Resursbank\\Woocommerce\\Modules\\Payment\\Converter\\Order\\Product::getSku :: IllegalValueException
* WOO-1177 Flytta setting för store från \[Advanced\] till \[API Settings\]
* WOO-1178 Remove order notes from management callback, and accept modify\_order callback
* WOO-1179 Skicka utan sufix för fee och shipping
* WOO-1181 \[Documentation\] Document that we do not support more than two decimals in prices
* WOO-1187 Cache clearing issue when chaining credentials
* WOO-1188 Moved Advanced section to the far right
* WOO-1189 Reload store list with AJAX
* WOO-1191 Remove order management callback handling
* WOO-1192 \\Resursbank\\Woocommerce\\Util\\Route::respondWithError passes Exception directly to frontend
* WOO-1193 Spegla fel från API ut i notes vid fel från aftershop anrop
* WOO-1194 Remove phpstan from qa scripts / config
* WOO-1195 Status handling is split and somewhat duplicated
* WOO-1197 Remove logging when ECom cannot init
* WOO-1198 Revamp totals in ordernotes
* WOO-1202 Replace UUID transactionId with time\(\)  \+ random int
* WOO-1208 PPW - tillägg av "type" som kvalificerar sig som ppw-betalmetod
* WOO-1220 Visa inte vissa rader från "Resurs vyn" på orderdetaljen i wc
* WOO-1225 Lägg till en kontroll vid statusändring på callback
* WOO-1226 Lägg tillbaka statusändring på thankYou-sidan
* WOO-1230 Döp om fliken "support info" till "About" och flytta längst till höger
* WOO-1236 Synka "reservera lagersaldo i x min"\(wc\) med "timeToLiveInMinutes" i skapa order\(resurs\)
* WOO-1239 Sätt tydliga krav på version i plugindata
* WOO-1240 Add setting to disable logs
* WOO-713 Skydda MAPI-pluginet från att köras om PHP är lägre än version 8.1
* WOO-727 Problem med deploys av vendor för mapi-modulen
* WOO-728 När nya pluginet är aktivt på namnbytt slug så funkar inte settingsurlen från pluginsidan
* WOO-745 När credentials för jwt är inskrivna krävs en extra omladdning för att stores skall bli synlig, det måste ske direkt efter att uppgifterna sparas
* WOO-756 getResurs\(\) beteende förändras då ecom gav ett ResursBank-object från ecom1
* WOO-769 Nullchecks i ResursDefault när betalmetoder inte blivit synkade ordentligt
* WOO-779 Störningar i getPaymentMethods när storeid inte är valt
* WOO-787 WooCommerce naturliga betalmetodslista har slutat visa våra betalmetoder \(bortsett från gatewayens namn\)
* WOO-789 Laga getPayment tillfälligt inför layoutbyte
* WOO-833 Laga gateway för kassan \(se till att betalmetoderna visas igen\)
* WOO-835 payment-methods stylingen har gått sönder
* WOO-836 "Spara" uppgifter i wp-admin funkar inte i Gerts instans \(och inte i våra heller\)
* WOO-838 När ingen butik är vald i admin
* WOO-840 Sync with broken ecom2
* WOO-843 Gatewayen i woo's adminpanel har ingen effekt alls
* WOO-848 Utred varför uuid inte går att lägga ordrar med längre \(Laga återstoden av gatewayen så att WOO-716  går att återuppta\)
* WOO-849 getAddress-formuläret försvann när gatewayen började förändras.
* WOO-863 ecompress-fel \(uteblivna betalmetoder i kassan\)
* WOO-870 Createpayment issues \(Session\+getAddress\)
* WOO-902 Kolla vad som behöver göras med customerType för att det ska funka
* WOO-915 Dead session may break checkout \(customerType\)
* WOO-928 När man gör fel vid getaddress
* WOO-930 govId skickas inte med till create \(\+getAddress silent exception\)
* WOO-931 phpmd notes för hantering av \_GET/\_REQUEST och "WP-internals"
* WOO-958 ThankYou-page
* WOO-959 Betalmetodens ID => Title fungerar inte på ecompress, men felfritt på "devservern" \(nödlösning är på plats men bör inte se ut som den gör just nu\).
* WOO-960 Dashboardversionen av Woocommerce strular \(NEED HELP!\)
* WOO-973 Generic errors caused by ecom-fetched gateways.
* WOO-975 Fatals \(WC\_Payment\_Gateway not found\) when plugin is unconfigured
* WOO-976 Can't reach coupon-editor
* WOO-984 Dubbla felmeddelanden när credentials inte är satta
* WOO-992 NATURAL/LEGAL-switchen för betalmetoder saknar effekt
* WOO-996 getAddress returnerar html efter json-svaret
* WOO-967 Legal felmappad vid create payment
* WOO-985 Felaktig moms på rabatter
* WOO-1019 phpmd warning
* WOO-1031 Dubbla "Order notes"
* WOO-1033 Ordrar som varit FROZEN unholdas inte
* WOO-1049 Discount lines in cancellation does not function properly
* WOO-1058 Solve order management conflicts
* WOO-1059 Settings tab missing from admin panel
* WOO-1070 Fullcancel-problem \(kod-order\)
* WOO-1072 Köp/Callback/Landingpage tappar paymentId
* WOO-1075 Text "in Resurs system" saknas på callbacknotiser
* WOO-1077 Får ett fel vid refund av belopp
* WOO-1078 Reverting order status during after-shop management can cause infite loop
* WOO-1080 Dubbla msg vid cancel “som inte ska funka”
* WOO-1081 When cancelling an order, before completing payment, you will receive an error messaghe stating it cannot be refunded
* WOO-1083 When a payment id is applied in metadata that does not correspond to a payment at Resurs Bank we receive an error
* WOO-1087 Multiple errors when payment methods cannot be fetched from API
* WOO-1089 Part payment settings will not reload annuity factors when changing payment method before you save for the first time
* WOO-1090 When cancelling / refunding a single order item we must re-instate discount
* WOO-1091 Disallowed operator
* WOO-1092 Part payment widget does not display on product pages
* WOO-1094 Cannot disable get address widget
* WOO-1097 API endpoints appear to always reply with HTTP 200 \(at least the part payment admin route\)
* WOO-1100 \\Resursbank\\Woocommerce\\Modules\\Order\\Filter\\DeleteItem::exec should not through Exception
* WOO-1101 \\Resursbank\\Woocommerce\\Modules\\Ordermanagement\\Cancelled::cancel should not through Exception
* WOO-1102 \\Resursbank\\Woocommerce\\Modules\\Ordermanagement\\Completed::capture should not through Exception
* WOO-1106 When fetching address we do not re-validate the input fields
* WOO-1108 Weird error messages when you cannot cancel payment
* WOO-1120 Callbacks hanteras inte \(ecompress\)
* WOO-1127 Refresh efter annullering av orderrad uppdaterar status till färdigbehandlad
* WOO-1130 WOO-1065 fix for legacy orders may cause PaymentInformation rendering to fail silently
* WOO-1131 Hantering av direktbetalnigar funkar inte vid capture
* WOO-1145 Fix PHPCS error
* WOO-1148 Legacy ordrar och order management \(capture, cancel, refund\)
* WOO-1149 We round to two decimals in several places, we should probably use the setting supplied by WC instead
* WOO-1153 In Order Management, messages produced when we change status etc. should refelect status name not code
* WOO-1154 WC3 orderskapande Samverkan med nya modulen
* WOO-1156 När man sparar om credentials med fel uppgifter, eller byter credentials
* WOO-1164 Betalmetoderna i kassan visas utanför min- och max-limit
* WOO-1169 Hämta felmeddelande från merchant API
* WOO-1174 Statushantering: failed till cancelled, cancelled till failed. Gäller orderstatus i admin
* WOO-1184 Statushantering efter Modify order
* WOO-1199 ecompress startup errors \(kodgenomgång\)
* WOO-1200 Clear out all settings. Enter API Credentials, save. Store appears selected but it's not.
* WOO-1209 Fel vid modify order
* WOO-1210 Ingen order note vid tillägg av avgift/frakt
* WOO-1211 Message fr Resurs saknas i order notes
* WOO-1212 Lista med stores visas inte efter delete av alla resurs settings i db och sparat nya client
* WOO-1213 PPW - Undersök varför PPW inte visas för vissa produktet
* WOO-1214 Dubblett på notes
* WOO-1215 Ö till ä i "vänligen"
* WOO-1216 Annullering av orderrader fallerar på sista orderraden
* WOO-1219 is\_admin check does not work for AJAX calls
* WOO-1221 Problem med SKU i kassan
* WOO-1222 Dubbla "Order notes"
* WOO-1223 Order noten saknas vid modify
* WOO-1224 Order "försvinner"
* WOO-1227 Admin endpoints are reachable without administration privileges
* WOO-1228 Duplicate event hook
* WOO-1229 \\Resursbank\\Woocommerce\\Modules\\Order\\Order::init is initiated by Shared, should be initated by admin
* WOO-1231 PPW - lägsta limit setting har slutat funka
* WOO-1233 Dubbel lagerminskning \(kom i samband med statushantering på thankYou-sidan\)
* WOO-1235 Fix whatever got broken in a reshuffling that breaks all sorts of stuff.
* WOO-1238 Skapa bara Resurs-order-notes för Resurs-betalmetoder 
