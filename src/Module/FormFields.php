<?php

namespace ResursBank\Module;

use Exception;
use ResursBank\Service\WordPress;
use WC_Checkout;
use WC_Settings_API;
use function in_array;
use function is_array;

/**
 * Class FormFields Self contained settings.
 *
 * @package ResursBank\Module
 * @since 0.0.1.0
 */
class FormFields extends WC_Settings_API
{
    /**
     * @var bool
     * @since 0.0.1.0
     */
    private static $allowMocking;

    /**
     * @var bool
     * @since 0.0.1.0
     */
    private static $showDeveloper;

    /**
     * @param string $section
     * @param string $id
     * @return array
     * @noinspection ParameterDefaultValueIsNotNullInspection
     * @since 0.0.1.0
     */
    public static function getFormFields($section = 'basic', $id = null)
    {
        if (empty($section)) {
            $section = 'basic';
        }

        // Basic settings. Returned to ResursDefault configuration.
        /** @noinspection HtmlUnknownTarget */
        $formFields = [
            'basic' => [
                'title' => __('Basic Resurs Bank API Settings', 'trbwc'),
                'enabled' => [
                    'id' => 'enabled',
                    'title' => __('Enable plugin checkout functions', 'trbwc'),
                    'desc' => __('Enabled', 'trbwc'),
                    'type' => 'checkbox',
                    'label' => __('Enable Resurs Bank', 'trbwc'),
                    'desc_tip' => __(
                        'This enables core functions of Resurs Bank, like the payment gateway, etc. ' .
                        'When disabled, after shop functions (i.e. debiting, annulling, etc) will still work.',
                        'trbwc'
                    ),
                    'default' => 'yes',
                ],
                'checkout_type' => [
                    'id' => 'checkout_type',
                    'title' => __('Checkout Type', 'trbwc'),
                    'type' => 'select',
                    'options' => [
                        'rco' => __(
                            'Resurs Checkout (embedded checkout by iframe)',
                            'trbwc'
                        ),
                        'simplified' => __(
                            'Integrated Checkout (simplified shopflow)',
                            'trbwc'
                        ),
                        'hosted' => __(
                            'Hosted Checkout',
                            'trbwc'
                        ),
                    ],
                    'custom_attributes' => [
                        'size' => 3,
                        'onchange' => 'resursUpdateFlowDescription(this)',
                    ],
                    'default' => 'rco',
                    'desc' => __(
                        'Chosen checkout type.',
                        'trbwc'
                    ),
                ],
                'environment' => [
                    'id' => 'environment',
                    'title' => __('Environment', 'trbwc'),
                    'type' => 'select',
                    'options' => [
                        'test' => __(
                            'Test',
                            'trbwc'
                        ),
                        'live' => __(
                            'Production',
                            'trbwc'
                        ),
                    ],
                    'custom_attributes' => [
                        'size' => 2,
                    ],
                    'default' => 'test',
                    'desc' => __(
                        'Defines if you are are live or just in test/staging. Default: test.',
                        'trbwc'
                    ),
                ],
                'login' => [
                    'id' => 'login',
                    'title' => __('Username', 'trbwc'),
                    'type' => 'text',
                    'desc' => __(
                        'Web services username, received from Resurs Bank.',
                        'trbwc'
                    ),
                    'default' => '',
                ],
                'login_production' => [
                    'id' => 'login_production',
                    'title' => __('Username (Production).', 'trbwc'),
                    'type' => 'text',
                    'desc' => __(
                        'Web services username, received from Resurs Bank.',
                        'trbwc'
                    ),
                    'default' => '',
                ],
                'password' => [
                    'id' => 'password',
                    'title' => __('Resurs Bank API password', 'trbwc'),
                    'type' => 'password',
                    'default' => '',
                    'desc' => __(
                        'API password, received from Resurs Bank. If your credentials are saved within the same ' .
                        'environment as the chosen one and you decide to validate them before saving, payment ' .
                        'methods and necessary data will update the same time. Otherwise, only credentials will be ' .
                        'saved.',
                        'trbwc'
                    ),
                    'custom_attributes' => [
                        'onload' => 'resursAppendCredentialCheck()',
                    ],
                ],
                'password_production' => [
                    'id' => 'password_production',
                    'title' => __('Resurs Bank API Password (Production).', 'trbwc'),
                    'type' => 'password',
                    'default' => '',
                    'desc' => __(
                        'API password, received from Resurs Bank. To validate and store the credentials ' .
                        'make sure you use the validation button. If you choose to not validate your credentials ' .
                        'here, and instead just save, you have to update the methods manually in the payment ' .
                        'methods section.',
                        'trbwc'
                    ),
                    'custom_attributes' => [
                        'onload' => 'resursAppendCredentialCheck()',
                    ],
                ],
                'country' => [
                    'id' => 'country',
                    'title' => __('Chosen merchant country', 'trbwc'),
                    'type' => 'text',
                    'default' => get_option('woocommerce_default_country'),
                    'css' => 'width: 100px',
                    'custom_attributes' => [
                        'readonly' => 'readonly',
                    ],
                    'desc' => __(
                        'Defines which country this plugin operates from. Credentials given by Resurs Bank are ' .
                        'limited to a specifc country. Default: Store address country.',
                        'trbwc'
                    ),
                ],
            ],
            'payment_methods' => [
                'title' => __('Payment methods and order handling', 'trbwc'),
                'payment_methods_settings' => [
                    'type' => 'title',
                    'title' => __('Payment methods, products and checkout', 'trbwc'),
                    'desc' => __(
                        'This section covers information for your current payment methods that is linked with your ' .
                        'API settings. You can not edit titles or descriptions at this page so if you need to ' .
                        'change such data you have to contact Resurs Bank support.',
                        'trbwc'
                    ),
                ],
                'order_id_type' => [
                    'id' => 'order_id_type',
                    'type' => 'select',
                    'title' => __('Order id numbering', 'trbwc'),
                    'desc' => __(
                        'Decide which kind of order id/reference that should be used when customers are ' .
                        'placing orders. If you let the plugin set the reference, the reference will be based on ' .
                        'a timestamp with an ending random number to make them unique (i.e. YYYYMMDDHHMMSS-UNIQUE).',
                        'trbwc'
                    ),
                    'default' => 'ecom',
                    'options' => [
                        'ecom' => __('Let plugin set the reference', 'trbwc'),
                        'postid' => __('Use WooCommerce internal post id as reference', 'trbwc'),
                    ],
                    'custom_attributes' => [
                        'size' => 2,
                    ],
                ],
                'payment_method_icons' => [
                    'id' => 'payment_method_icons',
                    'title' => __('Checkout method logotypes', 'trbwc'),
                    'type' => 'select',
                    'default' => 'none',
                    'options' => [
                        'none' => __('Prefer to not display logotypes', 'trbwc'),
                        'woocommerce_icon' => __('Display logotypes as WooCommerce default', 'trbwc'),
                        'only_specifics' => __('Display icons only if they are of customized', 'trbwc'),
                        'specifics_and_resurs' => __('Display Resurs branded and customized icons', 'trbwc'),
                    ],
                    'custom_attributes' => [
                        'size' => 4,
                    ],
                    'desc' => __(
                        'If there are branded payment methods in your checkout, that you prefer to display, choose ' .
                        'your best option here. Observe that this option is entirely dependent on your theme and ' .
                        'no layout are rendered through this as we use the default icon setup in WooCommerce to ' .
                        'show the icons.',
                        'trbwc'
                    ),
                ],
                'streamline_payment_fields' => [
                    'id' => 'streamline_payment_fields',
                    'type' => 'checkbox',
                    'title' => __('Applicant fields are always visible', 'trbwc'),
                    'desc' => __('Enabled', 'trbwc'),
                    'default' => 'no',
                    'desc_tip' => __(
                        'The applicant fields that Resurs Bank is using to handle payments is normally, inherited ' .
                        'from WooCommerce standard billing fields in the checkout. You can however enable them ' .
                        'here, if you want your customers to see them anyway.',
                        'trbwc'
                    ),
                ],
                'get_address_form' => [
                    'id' => 'get_address_form',
                    'type' => 'checkbox',
                    'title' => __('Use address information lookup service', 'trbwc'),
                    'desc' => __('Enabled', 'trbwc'),
                    'default' => 'yes',
                    'desc_tip' => __(
                        'This enables address lookup forms (getAddress) in checkout, when available. ' .
                        'Countries currently supported is SE (government id) and NO (phone number).',
                        'trbwc'
                    ),
                ],
                'get_address_form_always' => [
                    'id' => 'get_address_form_always',
                    'type' => 'checkbox',
                    'title' => __('Always show government id field from address service', 'trbwc'),
                    'desc' => __('Enabled', 'trbwc'),
                    'default' => 'no',
                    'desc_tip' => __(
                        'With this setting enabled, the getAddress form will always be shown, regardless of country ' .
                        'compatibility.',
                        'trbwc'
                    ),
                ],
                'rco_method_titling' => [
                    'id' => 'rco_method_titling',
                    'type' => 'select',
                    'options' => [
                        'default' => __('Default title.', 'trbwc'),
                        'id' => __('Use the ID of the chosen payment method.', 'trbwc'),
                        'description' => __('Use the description of the chosen payment method.', 'trbwc'),
                    ],
                    'default' => 'default',
                    'custom_attributes' => [
                        'size' => 3,
                    ],
                    'desc' => __(
                        'When payment methods are mentioned in order data and Resurs Checkout payments, you can ' .
                        'choose how it should be displayed. Selecting anything but the default value will display ' .
                        'the ID or description of a chosen payment method instead of "Resurs Bank AB".',
                        'trbwc'
                    ),
                ],
                'rco_iframe_position' => [
                    'id' => 'rco_iframe_position',
                    'title' => 'Resurs Checkout Position',
                    'desc' => __(
                        'Defines where in the checkout the iframe should be placed. Preferred position is after ' .
                        'the checkout form and also default. This setting is also configurable with filters.',
                        'trbwc'
                    ),
                    'type' => 'select',
                    'options' => [
                        'after_checkout_form' => __('After checkout form (Default).', 'trbwc'),
                        'checkout_before_order_review' => __('Before order review.', 'trbwc'),
                    ], // The options are based on available filters in WooCommerce.
                    'default' => 'after_checkout_form',
                    'custom_attributes' => [
                        'size' => 2,
                    ],
                ],
                'part_payment_template' => [
                    'id' => 'part_payment_template',
                    'title' => __('Part payment template', 'rbwc'),
                    'desc' => __(
                        'When you enable the part payment options for products, you can choose your own ' .
                        'template to display. Templates are built on WordPress pages. If you want to show a custom ' .
                        'page, you may choose which page you want to show here. Shortcodes that can be use: ' .
                        '[currency], [monthlyPrice], [monthlyDuration], [methodId], [methodDescription]. If you use ' .
                        'a custom text [monthlyPrice] will be delivered without the currency, so you have to add ' .
                        'that part yourself.',
                        'trbwc'
                    ),
                    'type' => 'select',
                    'options' => WordPress::applyFilters('getPartPaymentPage', []),
                ],
                'payment_methods_settings_end' => [
                    'id' => 'payment_methods_settings_end',
                    'type' => 'sectionend',
                ],
                'order_status_section' => [
                    'id' => 'order_status_section',
                    'type' => 'title',
                    'title' => 'Order Status Mapping',
                ],
                'accept_rejected_callbacks' => [
                    'id' => 'accept_rejected_callbacks',
                    'type' => 'checkbox',
                    'title' => __('Accept rejected callbacks', 'trbwc'),
                    'desc' => __('Enabled', 'trbwc'),
                    'default' => 'no',
                    'desc_tip' => __(
                        'When Resurs Bank has a callback delivery where the order does not exist in the system, the ' .
                        'plugin will respond with another HTTP code. If callbacks from Resurs Bank is ' .
                        'repeatedly sending too many messages of this kind due to any kind of errors ' .
                        '(like loops, etc), this option allows the plugin to reply with a response that ' .
                        'says that the callback was successful anyway.',
                        'trbwc'
                    ),
                ],
                'order_instant_finalization_status' => [
                    'id' => 'order_instant_finalization_status',
                    'title' => 'Automatically debited order status',
                    'type' => 'select',
                    'default' => 'default',
                    'options' => [
                        'default' => __('Use default (Completed)', 'resurs-bank-payment-gateway-for-woocommerce'),
                        'processing' => __('Processing', 'resurs-bank-payment-gateway-for-woocommerce'),
                        'credited' => __('Credited (refunded)', 'resurs-bank-payment-gateway-for-woocommerce'),
                        'completed' => __('Completed', 'resurs-bank-payment-gateway-for-woocommerce'),
                        'pending' => __('Pending (on-hold)', 'resurs-bank-payment-gateway-for-woocommerce'),
                        'annulled' => __('Annulled (cancelled)', 'resurs-bank-payment-gateway-for-woocommerce'),
                    ],
                ],
                'order_instant_finalization_methods' => [
                    'id' => 'order_instant_finalization_methods',
                    'title' => 'Payment methods defined as automatically debited',
                    'type' => 'select',
                    'options' => WordPress::applyFilters('getAvailableAutoDebitMethods', []),
                    'default' => 'default',
                    'custom_attributes' => [
                        'size' => count(WordPress::applyFilters('getAvailableAutoDebitMethods', [])),
                        'multiple' => 'multiple',
                    ],
                ],
                'order_status_section_end' => [
                    'id' => 'order_status_section_end',
                    'type' => 'sectionend',
                ],
                'payment_methods_list' => [
                    'type' => 'methodlist',
                ],
                'payment_methods_button' => [
                    'type' => 'button',
                    'action' => 'button',
                    'title' => __('Update payment methods and annuity factors', 'trbwc'),
                    'custom_attributes' => [
                        'onclick' => 'getResursPaymentMethods()',
                    ],
                ],
                'payment_methods_list_end' => [
                    'type' => 'sectionend',
                ],
                'callbacks_list' => [
                    'type' => 'callbacklist',
                ],
                'callbacks_button' => [
                    'type' => 'button',
                    'action' => 'button',
                    'title' => __('Update callbacks', 'trbwc'),
                    'custom_attributes' => [
                        'onclick' => 'getResursCallbacks()',
                    ],
                ],
                'trigger_callback_button' => [
                    'type' => 'button',
                    'action' => 'button',
                    'title' => __('Request test from Resurs Bank', 'trbwc'),
                    'custom_attributes' => [
                        'onclick' => 'getResursCallbackTest()',
                    ],
                ],
                'callbacks_list_end' => [
                    'type' => 'sectionend',
                ],
                'payment_method_annuity' => [
                    'title' => __('Part payment settings', 'trbwc'),
                    'desc' => __(
                        'If you have part payment options in any of your payment methods, this is where ' .
                        'you configure how the prices are shown in your product view and similar.',
                        'trbwc'
                    ),
                    'type' => 'title',
                ],
                'payment_method_annuity_end' => [
                    'type' => 'sectionend',
                ],
            ],
            'fraud_control' => [
                'title' => __('Fraud control', 'trbwc'),
                'fraud_finalization_section' => [
                    'type' => 'title',
                    'title' => __('How to handle fraud and debiting', 'trbwc'),
                    'desc' => sprintf(
                        __(
                            'This section configures how fraud and finalizations should be handled in the ' .
                            'integrated (simplified flow) and hosted checkout (not Resurs Checkout!). ' .
                            'It is strongly recommended to keep the settings disabled and let callbacks handle ' .
                            'the rest, unless you are a travel company that rely on non fraudulent behaviours. ' .
                            'The settings below makes sure that orders that is frozen when the order has been ' .
                            'handled are automatically annulled. If the orders in other hands are healthy and booked ' .
                            'you can also set the process to automatically debit/finalize the order with the setup ' .
                            'below. ' .
                            'For more information, see <a href="%s" target="_blank">%s</a>.',
                            'trbwc'
                        ),
                        'https://test.resurs.com/docs/display/ecom/paymentData',
                        'https://test.resurs.com/docs/display/ecom/paymentData'
                    ),
                ],
                'waitForFraudControl' => [
                    'id' => 'waitForFraudControl',
                    'type' => 'checkbox',
                    'title' => __('Wait for fraud control', 'trbwc'),
                    'desc' => __('Enabled', 'trbwc'),
                    'desc_tip' => __(
                        'The checkout process waits until the fraud control is finished at Resurs Bank ' .
                        'and the order is handled synchronously. If this setting is disabled, Resurs Bank must be ' .
                        'able to reach your system with callbacks to be able to deliver the result.',
                        'trbwc'
                    ),
                    'default' => 'no',
                    'custom_attributes' => [
                        'onchange' => 'getResursFraudFlags(this)',
                    ],
                ],
                'annulIfFrozen' => [
                    'id' => 'annulIfFrozen',
                    'type' => 'checkbox',
                    'title' => __('Annul frozen orders', 'trbwc'),
                    'desc' => __('Enabled', 'trbwc'),
                    'desc_tip' => __(
                        'If Resurs Bank freezes a payment due to fraud, the order will automatically be annulled. ' .
                        'By default, the best practice is to handle all annulments asynchronously with callbacks. ' .
                        'Callback event name is ANNUL.',
                        'trbwc'
                    ),
                    'default' => 'no',
                    'custom_attributes' => [
                        'onchange' => 'getResursFraudFlags(this)',
                    ],
                ],
                'finalizeIfBooked' => [
                    'id' => 'finalizeIfBooked',
                    'type' => 'checkbox',
                    'title' => __('Automatically debit if booked', 'trbwc'),
                    'desc' => __('Enabled', 'trbwc'),
                    'desc_tip' => __(
                        'Orders are automatically debited (finalized) if the fraud control passes. ' .
                        'By default, the best practice is to handle all finalizations asynchronously with callbacks. ' .
                        'Callback event name is FINALIZATION.',
                        'trbwc'
                    ),
                    'default' => 'no',
                    'custom_attributes' => [
                        'onchange' => 'getResursFraudFlags(this)',
                    ],
                ],
                'fraud_finalization_section_end' => [
                    'type' => 'sectionend',
                ],
                'rco_customer_behaviour' => [
                    'type' => 'title',
                    'title' => __('Resurs Checkout customer interaction behaviour', 'trbwc'),
                ],
                'rco_customer_behaviour_end' => [
                    'id' => 'rco_customer_behaviour_end',
                    'type' => 'sectionend',
                ],
            ],
            'advanced' => [
                'title' => __('Advanced Merchant', 'trbwc'),
                'complex_api_section' => [
                    'type' => 'title',
                    'title' => __('Advanced API', 'trbwc'),
                ],
                'rco_paymentid_age' => [
                    'id' => 'rco_paymentid_age',
                    'title' => __('Resurs Checkout paymentId maximum age.', 'trbwc'),
                    'type' => 'text',
                    'desc' => __(
                        'Defined in seconds, how long a preferred payment id can live before it is renewed in a ' .
                        'current session. This setting is necessary as we use the id to track cart updates ' .
                        'which very much prevents malicious cart manipulation. It also allows customers to reload ' .
                        'the checkout page and still use the same payment id. When a payment is successful, the ' .
                        'preferred payment id will also be reset.',
                        'trbwc'
                    ),
                    'default' => '3600',
                ],
                'queue_order_statuses_on_success' => [
                    'id' => 'queue_order_statuses_on_success',
                    'title' => __('Queue order statuses on successpage', 'trbwc'),
                    'desc' => __('Enabled', 'trbwc'),
                    'desc_tip' => __(
                        'If you suspect that there may be race conditions between order status updates in the ' .
                        'customer-success landing page, and the order statuses updated with callbacks you can ' .
                        'enable this option to queue not only the callback updates but also the other updates.',
                        'trbwc'
                    ),
                    'type' => 'checkbox',
                    'default' => 'no',
                ],
                'discard_coupon_vat' => [
                    'id' => 'discard_coupon_vat',
                    'title' => __('Do not add VAT to discounts', 'trbwc'),
                    'desc' => __('Enabled', 'trbwc'),
                    'desc_tip' => __(
                        'When order rows are added to Resurs Bank API, the VAT is applied on the coupon amount ' .
                        'excluding tax. To handle the discount without vat and instead use the full including tax ' .
                        'amount as a discount, you can enable this feature.',
                        'trbwc'
                    ),
                    'type' => 'checkbox',
                    'default' => 'no',
                ],
                'prevent_rounding_panic' => [
                    'id' => 'prevent_rounding_panic',
                    'title' => __('Prevent rounding errors', 'trbwc'),
                    'desc' => __('Enabled', 'trbwc'),
                    'desc_tip' => __(
                        'WooCommerce are able to show prices rounded with 0 decimals. It is however widely known ' .
                        'and confirmed that payment gateways may have problems with tax calculation, when the ' .
                        'decimals are fewer than two. With this setting enabled, the plugin will try to override the ' .
                        'decimal setup as long as it is set to lower than 2. If you disable this feature, you also ' .
                        'confirm that you are willingly using a, for the platform, unsupported feature. If you\'ve ' .
                        'not already done it, it is recommended to instead increase the number of decimals to 2 or ' .
                        'higher.',
                        'trbwc'
                    ),
                    'type' => 'checkbox',
                    'default' => 'yes',
                ],
                'deprecated_interference' => [
                    'id' => 'deprecated_interference',
                    'title' => __('Can interact with old-plugin orders', 'trbwc'),
                    'desc' => __('Enabled', 'trbwc'),
                    'desc_tip' => __(
                        'Enabling this feature allows the plugin to enter orders created with the old plugin.',
                        'trbwc'
                    ),
                    'type' => 'checkbox',
                    'default' => 'no',
                ],
                'store_api_history' => [
                    'id' => 'store_api_history',
                    'title' => __('Store API history in orders', 'trbwc'),
                    'desc' => __('Enabled', 'trbwc'),
                    'desc_tip' => __(
                        'If this setting is active, the first time you view a specific order API data will be stored ' .
                        'for it. This means that it will be possible to go back to prior orders and view them even ' .
                        'after you change the user credentials.',
                        'trbwc'
                    ),
                    'type' => 'checkbox',
                    'default' => 'yes',
                ],
                'api_wsdl' => [
                    'id' => 'api_wsdl',
                    'title' => __('WSDL requests are cached', 'trbwc'),
                    'type' => 'select',
                    'options' => [
                        'default' => __(
                            'Default: Only for production/live environment',
                            'trbwc'
                        ),
                        'both' => __(
                            'Both for production/live and test/staging',
                            'trbwc'
                        ),
                        'none' => __(
                            'Do not cache WSDL',
                            'trbwc'
                        ),
                    ],
                    'desc' => __(
                        'This setting defines how SOAP requests are being made to Resurs Bank. It is ' .
                        'usually recommended to keep requests cached (meaning wsdl and data required for a SOAP ' .
                        'call to work, are stored locally on your server). During development it sometimes ' .
                        'better to run tests uncached, however, it is not recommended in a production since ' .
                        'this directly affects network performance (since each SOAP call will include an extra ' .
                        'network request to the API first).'
                    ),
                    'default' => 'default',
                ],
                'complex_api_section_end' => [
                    'type' => 'sectionend',
                ],
                'complex_developer_section' => [
                    'type' => 'title',
                    'title' => __('Developer Section', 'trbwc'),
                ],
                'logging' => [
                    'title' => __('Logging', 'trbwc'),
                    'type' => 'title',
                    'desc' => __(
                        'Default for this plugin is to log a fair amount of data for you. However, there is also ' .
                        'also much debug data for developers available, that you normally not need. In this section ' .
                        'you can choose the extras you want to see in your logs.',
                        'trbwc'
                    ),
                ],
                'can_log_order_events' => [
                    'id' => 'can_log_order_events',
                    'type' => 'checkbox',
                    'title' => __('Details at order events for merchants', 'trbwc'),
                    'desc' => __('Yes', 'trbwc'),
                    'desc_tip' => __(
                        'Detailed order events are data that normally passes without any sound. ' .
                        'Things like initial order creations and clicks could show up in your logs.',
                        'trbwc'
                    ),
                    'default' => 'no',
                ],
                'can_log_info' => [
                    'id' => 'can_log_info',
                    'type' => 'checkbox',
                    'title' => __('Log INFO events', 'trbwc'),
                    'desc' => __('Yes', 'trbwc'),
                    'desc_tip' => __(
                        'Log events that flows under severity INFO. Logs affected is for example mocking events.',
                        'trbwc'
                    ),
                    'default' => 'no',
                ],
                'can_log_order_developer' => [
                    'id' => 'can_log_order_developer',
                    'type' => 'checkbox',
                    'title' => __('Developer based details at order events', 'trbwc'),
                    'desc' => __('Yes', 'trbwc'),
                    'desc_tip' => __(
                        'Works like details for merchants, but this adds debugging information that may only be ' .
                        'relevant for developers.',
                        'trbwc'
                    ),
                    'default' => 'no',
                ],
                'can_log_junk' => [
                    'id' => 'can_log_junk',
                    'type' => 'checkbox',
                    'title' => __('Deep details', 'trbwc'),
                    'desc' => __('Yes', 'trbwc'),
                    'desc_tip' => __(
                        'Things that only developers would have interest in. Logs may be junky with this ' .
                        'option enabled.',
                        'trbwc'
                    ),
                    'default' => 'no',
                ],
                'can_log_backend' => [
                    'id' => 'can_log_backend',
                    'type' => 'checkbox',
                    'title' => __('Backend requests', 'trbwc'),
                    'desc' => __('Yes', 'trbwc'),
                    'desc_tip' => __(
                        'Log backend events triggered by the ajaxify executor.',
                        'trbwc'
                    ),
                    'default' => 'no',
                ],
                'show_developer' => [
                    'title' => __('Activate Advanced Tweaking Mode (Developer)', 'trbwc'),
                    'desc' => __(
                        'Activate Advanced Tweaking Mode (you might need an extra reload after save)',
                        'trbwc'
                    ),
                    'desc_tip' => __(
                        'The developer section is normally nothing you will need, unless you are a very advanced ' .
                        'administrator/developer/merchant that likes to configure a little bit over the limits. ' .
                        'If you know what you are doing, feel free to activate this section.',
                        'trbwc'
                    ),
                    'type' => 'checkbox',
                    'default' => 'no',
                ],
                'complex_developer_section_end' => [
                    'type' => 'sectionend',
                ],
            ],
            'information' => [
                'title' => __('Support', 'trbwc'),
            ],
        ];

        $formFields = WordPress::applyFilters('getCustomFormFields', $formFields, $section);

        if ($section === 'all') {
            $return = $formFields;
        } else {
            $return = isset($formFields[$section]) ? self::getTransformedIdArray($formFields[$section], $id) : [];
        }

        return $return;
    }

    /**
     * Transform options into something that fits in a WC_Settings_Page-block.
     *
     * @param $array
     * @param $add
     * @return string
     * @since 0.0.1.0
     */
    public static function getTransformedIdArray($array, $add)
    {
        $return = $array;

        if (!empty($add)) {
            foreach ($array as $itemKey => $item) {
                if (is_array($item)) {
                    if (isset($item['id'])) {
                        $item['id'] = sprintf('%s_%s', $add, $item['id']);
                    } else {
                        $item['id'] = sprintf('%s_%s', $add, $itemKey);
                    }
                    $return[$itemKey] = $item;
                } else {
                    unset($return[$itemKey]);
                }
            }
        }

        return $return;
    }

    /**
     * @param $formData
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getFieldButton($formData)
    {
        (new self())->getFieldButtonApi($formData);
    }

    /**
     * @param $formData
     * @throws Exception
     * @since 0.0.1.0
     */
    public function getFieldButtonApi($formData)
    {
        $action = isset($formData['action']) && !empty($formData['action']) ? $formData['action'] : 'button';

        $allowedFormData = [
            Data::getPrefix('admin_payment_methods_button'),
            Data::getPrefix('admin_callbacks_button'),
            Data::getPrefix('admin_trigger_callback_button'),
        ];

        if (isset($formData['id']) && in_array($formData['id'], $allowedFormData, true)) {
            $formArray = $formData;
            $formArray['action'] = $action; // Our action
            $formArray['custom_attributes'] = $this->get_custom_attribute_html($formData);
            echo Data::getGenericClass()->getTemplate('adminpage_button', $formArray);
        }
    }

    /**
     * Filter based addon.
     * Do not use getResursOption in this request as this may cause infinite loops.
     *
     * @param $currentArray
     * @param $section
     * @return array
     * @since 0.0.1.0
     */
    public static function getDeveloperTweaks($currentArray, $section)
    {
        $return = $currentArray;

        $developerArray = [
            'developer' => [
                'dev_section' => [
                    'type' => 'title',
                    'title' => __('Developers Section', 'trbwc'),
                    'desc' => sprintf(
                        __(
                            'This section is for very advanced tweaking only. It is not enabled and visible by ' .
                            'default for security reasons. Proceed at your own risk!',
                            'trbwc'
                        )
                    ),
                ],
                'title' => __('Developer Settings', 'trbwc'),
                'plugin_section' => [
                    'type' => 'title',
                    'title' => 'Plugin Settings',
                ],
                'priorVersionsDisabled' => [
                    'id' => 'priorVersionsDisabled',
                    'title' => __('Disable RB 2.x', 'trbwc'),
                    'type' => 'checkbox',
                    'desc' => __(
                        'Disable prior similar versions of the Resurs Bank plugin (v2.x-series) - ' .
                        'You might need an extra reload after save',
                        'trbwc'
                    ),
                    'desc_tip' => __(
                        'This setting will disable, not entirely, but the functions in Resurs Bank Gateway v2.x ' .
                        'with help from filters in that release.',
                        'trbwc'
                    ),
                    'default' => 'yes',
                ],
                'dev_section_end' => [
                    'type' => 'sectionend',
                ],
                'bleeding_edge_settings' => [
                    'type' => 'title',
                    'title' => 'Bleeding Edge',
                ],
                'bleeding_edge' => [
                    'id' => 'bleeding_edge',
                    'title' => __('Bleeding Edge Checkout Technology', 'trbwc'),
                    'type' => 'checkbox',
                    'desc' => __('Enable', 'trbwc'),
                    'desc_tip' => __(
                        'Enable features that is still under development. The features enabled here are not ' .
                        'guaranteed to work in production environments and should only be enabled by a developer.' .
                        'Bleeding edge mode can currently only be used in test. Also please note, that features' .
                        'within this area, requires higher versions of PHP.',
                        'trbwc'
                    ),
                    'default' => 'no',
                ],
                'bleeding_edge_settings_end' => [
                    'type' => 'sectionend',
                ],
                'admin_tweaking_section' => [
                    'type' => 'title',
                    'title' => 'Administration Tweaking',
                ],
                'nonce_trust_admin_session' => [
                    'id' => 'nonce_trust_admin_session',
                    'title' => __('Trust is_admin before frontend nonces.', 'trbwc'),
                    'type' => 'checkbox',
                    'desc' => __(
                        'Yes, do trust them please.',
                        'trbwc'
                    ),
                    'desc_tip' => __(
                        'For some places in the admin panel, we use nonces as an extra security layer when it comes ' .
                        'to requests like updating callbacks, payment methods, etc. Sometimes nonces expires too ' .
                        'quickly and breaks requests in wp_admin. Enable this feature to start trusting is_admin() ' .
                        'during ajax request primarily and nonces secondarily. is_admin is normally a security layer ' .
                        'that prevents unknonwn requests to be executed.',
                        'trbwc'
                    ),
                    'default' => 'no',
                ],
                'admin_tweaking_section_end' => [
                    'type' => 'sectionend',
                ],
                'customer_checkout_tweaking_section' => [
                    'type' => 'title',
                    'title' => 'Customer & Checkout Tweaking',
                ],
                'simulate_real_getaddress' => [
                    'id' => 'simulate_real_getaddress',
                    'title' => __('Activate "real people"-mode in test, for getAddress.', 'trbwc'),
                    'type' => 'checkbox',
                    'desc' => __(
                        'Enable.',
                        'trbwc'
                    ),
                    'desc_tip' => __(
                        'Required production credentials available: When activating this mode, getAddress will use ' .
                        'real lookups for getAddress rather than the mocked data.',
                        'trbwc'
                    ),
                    'default' => 'no',
                ],
                'allow_mocking' => [
                    'id' => 'allow_mocking',
                    'title' => __('Allow mocked behaviours', 'trbwc'),
                    'type' => 'checkbox',
                    'desc' => __(
                        'Enable.',
                        'trbwc'
                    ),
                    'desc_tip' => __(
                        'This setting enables mocked behaviours and data on fly, during tests.',
                        'trbwc'
                    ),
                    'default' => 'no',
                ],
                'customer_checkout_tweaking_section_end' => [
                    'type' => 'sectionend',
                ],
                'order_tweaking_section' => [
                    'type' => 'title',
                    'title' => 'Order Tweaking',
                ],
                'order_note_prefix' => [
                    'id' => 'order_note_prefix',
                    'title' => __('Prefix for order and status notes', 'trbwc'),
                    'type' => 'text',
                    'desc' => __(
                        'When orders are updated with new statuses, or gets new notifications this is how we are ' .
                        'prefixing the notes. Default (empty) is "trbwc".',
                        'trbwc'
                    ),
                    'default' => '',
                ],
                'order_tweaking_section_end' => [
                    'type' => 'sectionend',
                ],
            ],
        ];

        $mockingTweaks = self::getMockingTweaks();

        if ((isset($section) && $section === 'all') || self::getShowDeveloper()) {
            $return = array_merge($return, $developerArray, $mockingTweaks);
        }

        return $return;
    }

    /**
     * @param $currentArray
     * @param $section
     * @return mixed
     * @since 0.0.1.0
     */
    public static function getMockingTweaks()
    {
        $return = [];
        if (!isset(self::$allowMocking)) {
            self::$allowMocking = Data::getResursOption('allow_mocking', null, false);
        }

        if (self::$allowMocking && Data::getResursOption('environment', null, false) === 'test') {
            $return['mocking'] = [
                'title' => __('Mocking & Testing', 'trbwc'),
                'mocking_section' => [
                    'type' => 'title',
                    'title' => __('Mocking Section', 'trbwc'),
                    'desc' => sprintf(
                        __(
                            'Section of Mocking & Tests. Are you not developing this plugin? Then you probably do ' .
                            'not need it either. The section is specifically placed hiddenly here, since the options ' .
                            'here are used to recreate events under very specific circumstances. For example, you ' .
                            'can mock errors from here, that you otherwise had to hardcode into the plugin. Options ' .
                            'here are normally enabled until the feature has been trigged once. After first ' .
                            'execution it will instantly become disabled automatically. The mocking section can only ' .
                            'be enabled when your environment is set to test and you explicitly allowed mocking on ' .
                            'your site.',
                            'trbwc'
                        )
                    ),
                ],
                'mock_update_payment_reference_failure' => [
                    'id' => 'mock_update_payment_reference_failure',
                    'title' => __('Fail on updatePaymentReference', 'trbwc'),
                    'type' => 'checkbox',
                    'desc' => __(
                        'Enable.',
                        'trbwc'
                    ),
                    'desc_tip' => __(
                        'This setting enables a fictive error on front-to-back calls during order creations where ' .
                        'updatePaymentReference occurs.',
                        'trbwc'
                    ),
                    'default' => 'no',
                ],
                'mock_create_iframe_exception' => [
                    'id' => 'mock_create_iframe_exception',
                    'title' => __(
                        'Fail on iframe creation',
                        'trbwc'
                    ),
                    'type' => 'checkbox',
                    'desc' => __(
                        'Enable.',
                        'trbwc'
                    ),
                    'desc_tip' => __(
                        'This setting enables a fictive error in the checkout where the iframe fails to render. This ' .
                        'has happened during development, where the current payment id used by the plugin ' .
                        'collided with an already existing order id at Resurs Bank.',
                        'trbwc'
                    ),
                    'default' => 'no',
                ],
                'mock_update_callback_exception' => [
                    'id' => 'mock_update_callback_exception',
                    'title' => __(
                        'Fail on callback update',
                        'trbwc'
                    ),
                    'type' => 'checkbox',
                    'desc' => __(
                        'Enable.',
                        'trbwc'
                    ),
                    'desc_tip' => __(
                        'This setting enables a fictive callback problem.',
                        'trbwc'
                    ),
                    'default' => 'no',
                ],
                'mock_empty_price_info_html' => [
                    'id' => 'mock_empty_price_info_html',
                    'title' => __(
                        'Fail retrieval of priceinfo',
                        'trbwc'
                    ),
                    'type' => 'checkbox',
                    'desc' => __(
                        'Enable.',
                        'trbwc'
                    ),
                    'desc_tip' => __(
                        'Ensure that the priceinfo box still shows data when no data has been retrieved from priceinfo.',
                        'trbwc'
                    ),
                    'default' => 'no',
                ],
                'mocking_section_end' => [
                    'type' => 'sectionend',
                ],
            ];
        }

        return $return;
    }

    /**
     * @return bool
     * @since 0.0.1.0
     */
    public static function getShowDeveloper()
    {
        if (!isset(self::$showDeveloper)) {
            self::$showDeveloper = Data::getResursOption('show_developer', null, false);
        }
        return self::$showDeveloper;
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getFieldDecimals()
    {
        if (wc_get_price_decimals() < 2 && Data::getResursOption('prevent_rounding_panic')) {
            echo Data::getGenericClass()->getTemplate('adminpage_general_decimals.phtml', [
                'pluginTitle' => Data::getPluginTitle(),
            ]);
        }
    }

    /**
     * @param WC_Checkout $checkout
     * @param bool $returnHtml
     * @return false|string|void
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getGetAddressForm($checkout, $returnHtml = false)
    {
        $getAddressFormAlways = (bool)Data::getResursOption('get_address_form_always');
        $customerTypeByConditions = Data::getCustomerType();
        $return = Data::getGenericClass()->getTemplate(
            'checkout_getaddress.phtml',
            [
                'customer_private' => __('Private person', 'trbwc'),
                'customer_company' => __('Company', 'trbwc'),
                'customer_type' => (null === $customerTypeByConditions) ? 'NATURAL' : $customerTypeByConditions,
                'customer_button_text' => WordPress::applyFilters('getAddressButtonText', __('Get address', 'trbwc')),
                'supported_country' => Data::isGetAddressSupported(),
                'get_address_form' => Data::canUseGetAddressForm(),
                'get_address_form_always' => $getAddressFormAlways,
            ]
        );
        if ($returnHtml) {
            return $return;
        }
        echo $return;
    }

    /**
     * Fetch payment methods list. formData is not necessary here since this is a very specific field.
     *
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getFieldMethodList()
    {
        $exception = null;
        $paymentMethods = [];
        $theFactor = Data::getResursOption('currentAnnuityFactor');
        $theDuration = (int)Data::getResursOption('currentAnnuityDuration');

        try {
            $paymentMethods = ResursBankAPI::getPaymentMethods();
            $annuityFactors = self::getAnnuityDropDown(ResursBankAPI::getAnnuityFactors(), $theFactor, $theDuration);
        } catch (Exception $e) {
            $exception = $e;
        }

        if (is_array($paymentMethods)) {
            $annuityEnabled = Data::getResursOption('currentAnnuityFactor');

            echo Data::getGenericClass()->getTemplate(
                'adminpage_paymentmethods.phtml',
                [
                    'paymentMethods' => $paymentMethods,
                    'annuityFactors' => $annuityFactors,
                    'exception' => $exception,
                    'annuityEnabled' => $annuityEnabled,
                    'environment' => Data::getResursOption('environment'),
                ]
            );
        }
    }

    /**
     * @param $annuityFactors
     * @return string
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function getAnnuityDropDown($annuityFactors, $theFactor, $theDuration)
    {
        $return = [];

        foreach ($annuityFactors as $id => $factorArray) {
            if (count($factorArray)) {
                $return[$id] = self::getRenderedFactors($id, $factorArray, $theFactor, $theDuration);
            } else {
                $return[$id] = '';
            }
        }

        return $return;
    }

    /**
     * @param $id
     * @param $factorArray
     * @return string
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function getRenderedFactors($id, $factorArray, $theFactor, $theDuration)
    {
        $options = null;
        foreach ($factorArray as $item) {
            $selected = '';
            if (($id === $theFactor) && (int)$theDuration === (int)$item->duration && empty($selected)) {
                $selected = 'selected';
            }
            $options .= sprintf(
                '<option value="%s" %s>%s</option>',
                $item->duration,
                $selected,
                $item->paymentPlanName
            );
        }

        $isFactorEnabled = Data::getResursOption('currentAnnuityFactor');
        $enabled = ($isFactorEnabled === $id) ? true : false;
        return Data::getGenericClass()->getTemplate('adminpage_annuity_selector.phtml', [
            'id' => $id,
            'options' => $options,
            'enabled' => $enabled,
        ]);
    }

    /**
     * Fetch payment methods list. formData is not necessary here since this is a very specific field.
     *
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getFieldCallbackList()
    {
        $exception = null;
        $callbacks = [];
        try {
            $callbacks = ResursBankAPI::getCallbackList();
        } catch (Exception $e) {
            $exception = $e;
        }

        if (is_array($callbacks)) {
            echo Data::getGenericClass()->getTemplate(
                'adminpage_callbacks.phtml',
                [
                    'callbacks' => $callbacks,
                    'exception' => $exception,
                ]
            );
        }
    }

    /**
     * @param null $key
     * @return array|mixed
     * @since 0.0.1.0
     */
    public static function getFieldString($key = null)
    {
        $fields = [
            'government_id' => __('Social security number', 'trbwc'),
            'phone' => __('Phone number', 'trbwc'),
            'mobile' => __('Mobile number', 'trbwc'),
            'email' => __('E-mail address', 'trbwc'),
            'government_id_contact' => __('Applicant government id', 'trbwc'),
            'card_number' => __('Card number', 'trbwc'),
        ];

        // If no key are sent here, it is probably a localization request.
        $return = $fields;

        if (!empty($key) && isset($fields[$key])) {
            $return = $fields[$key];
        }

        return $return;
    }

    /**
     * @param $key
     * @return bool
     * @since 0.0.1.0
     */
    public static function canDisplayField($key)
    {
        return in_array($key, [
            'government_id',
            'government_id_contact',
            'card_number',
        ]);
    }

    /**
     * @param null $key
     * @return array
     * @since 0.0.1.0
     */
    public static function getSpecificTypeFields($key = null)
    {
        $return = [
            'INVOICE' => [
                'government_id',
                'phone',
                'mobile',
                'email',
            ],
            'INVOICE_LEGAL' => [
                'government_id',
                'phone',
                'mobile',
                'email',
                'government_id_contact',
            ],
            'CARD' => [
                'government_id',
            ],
            'REVOLVING_CREDIT' => [
                'government_id',
                'mobile',
                'email',
            ],
            'PART_PAYMENT' => [
                'government_id',
                'phone',
                'mobile',
                'email',
            ],
            'undefined' => [
                'government_id',
                'phone',
                'mobile',
                'email',
            ],
        ];

        if (!empty($key) && isset($return[$key])) {
            $return = $return[$key];
        } else {
            $return = $return['undefined'];
        }

        return WordPress::applyFilters('getSpecificTypeFields', $return, $key);
    }
}
