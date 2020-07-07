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
     */
    public static function getFormFields($section = 'basic', $id = '')
    {
        if (empty($section)) {
            $section = 'basic';
        }

        // Basic settings. Returned to ResursDefault configuration.
        $formFields = [
            'basic' => [
                'title' => __('Plugin and account settings', 'trbwc'),
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
                ],
                'country' => [
                    'id' => 'country',
                    'title' => __('Country', 'trbwc'),
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
            'advanced' => [
                'title' => __('Advanced Settings', 'trbwc'),
                'show_developer' => [
                    'title' => __('Activate developer section', 'rbwc'),
                    'desc' => __('Activate developer section', 'trbwc'),
                    'desc_tip' => __(
                        'The developer section is normally nothing you will need, unless you are a very advanced ' .
                        'administrator that likes to configure a little bit over the limits. If you know what you ' .
                        'are doing, feel free to activate this section.',
                        'trbwc'
                    ),
                    'type' => 'checkbox',
                    'default' => 'no',
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
