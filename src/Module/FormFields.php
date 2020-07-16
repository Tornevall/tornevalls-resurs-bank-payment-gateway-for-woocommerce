<?php

namespace ResursBank\Module;

use ResursBank\Helper\WordPress;

/**
 * Class FormFields Self contained settings.
 * @package ResursBank\Module
 * @since 0.0.1.0
 */
class FormFields
{
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
        $formFields = [
            'basic' => [
                'title' => __('Basic Resurs Bank API Settings', 'trbwc'),
                'enabled' => [
                    'id' => 'enabled',
                    'title' => __('Enable/Disable', 'trbwc'),
                    'desc' => __('Enable/Disable', 'trbwc'),
                    'type' => 'checkbox',
                    'label' => __('Enable Resurs Bank', 'trbwc'),
                    'desc_tip' => __(
                        'This enables core functions of Resurs Bank, like the payment gateway, etc. ' .
                        'When disabled, after shop functions (i.e. debiting, annulling, etc) will still work.',
                        'trbwc'
                    ),
                    'default' => 'yes',
                ],
                'environment' => [
                    'id' => 'environment',
                    'title' => __('Environment', 'trbwc'),
                    'type' => 'select',
                    'options' => [
                        'test' => __(
                            'Test/Staging',
                            'trbwc'
                        ),
                        'live' => __(
                            'Production/Live',
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
                    'title' => __('Resurs Bank webservices username', 'trbwc'),
                    'type' => 'text',
                    'desc' => __(
                        'Web services username, received from Resurs Bank.',
                        'trbwc'
                    ),
                    'default' => '',
                ],
                'password' => [
                    'id' => 'password',
                    'title' => __('Resurs Bank webservices password', 'trbwc'),
                    'type' => 'password',
                    'default' => '',
                    'desc' => __(
                        'Web services password, received from Resurs Bank.',
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
            ],
            'customers_orders' => [
                'title' => __('Customers and orders', 'trbwc'),
                'fraud_finalization_section' => [
                    'type' => 'title',
                    'title' => __('Callbacks: Automatic fraud- and debit handling', 'trbwc'),
                    'desc' => sprintf(
                        __(
                            'This section configures how fraud and finalizations should be handled in the ' .
                            'integrated/simplified and hosted checkout (not Resurs Checkout!). In short, the settings ' .
                            'below makes sure that orders that is frozen when the order has been handled are ' .
                            'automatically annulled. If the orders in other hands are healthy and booked you can ' .
                            'also set the process to automatically debit/finalize the order with the flagset below. ' .
                            'Observe that some of the settings can not be enabled simultaneously. ' .
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
                    'desc' => __('Enabled/disabled', 'trbwc'),
                    'desc_tip' => __(
                        'The checkout process waits until the fraud control is finished at Resurs Bank ' .
                        'and the order is handled synchronously. If this setting is disabled, Resurs Bank must be ' .
                        'able to reach your system with callbacks to be able to deliver the result. ' .
                        'Callback event name is AUTOMATIC_FRAUD_CONTROL.',
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
                    'desc' => __('Enabled/disabled', 'trbwc'),
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
                    'desc' => __('Enabled/disabled', 'trbwc'),
                    'desc_tip' => __(
                        'Orders are automatically debited (finalized) if the fraud control passes. ',
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
                'title' => __('Advanced Settings', 'trbwc'),
                'complex_api_section' => [
                    'type' => 'title',
                    'title' => __('Advanced API', 'trbwc'),
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
                            'Not at all, please',
                            'trbwc'
                        ),
                    ],
                    'default' => 'default',
                ],
                'complex_api_section_end' => [
                    'type' => 'sectionend',
                ],
                'complex_developer_section' => [
                    'type' => 'title',
                    'title' => __('Developer Section', 'trbwc'),
                ],
                'show_developer' => [
                    'title' => __('Activate developer mode', 'trbwc'),
                    'desc' => __('Activate developer mode (you might need an extra reload after save)', 'trbwc'),
                    'desc_tip' => __(
                        'The developer section is normally nothing you will need, unless you are a very advanced ' .
                        'administrator that likes to configure a little bit over the limits. If you know what you ' .
                        'are doing, feel free to activate this section.',
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

        $formFields = WordPress::applyFilters('getDependentSettings', $formFields, $section);

        if ($section === 'all') {
            $return = $formFields;
        } else {
            $return = isset($formFields[$section]) ? self::getTransformedIdArray($formFields[$section], $id) : [];
        }

        return $return;
    }

    /**
     * Transform options into something that fits in a WC_Settings_Page-block.
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
}
