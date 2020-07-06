<?php

namespace ResursBank\Module;

/**
 * Class FormFields Self contained settings.
 * @package ResursBank\Module
 * @since 0.0.1.0
 */
class FormFields
{
    /**
     * @param bool $getBasic
     * @return array
     */
    public static function getFormFields($getBasic = null)
    {
        // Basic settings. Returned to ResursDefault configuration.
        $basic = [
            'enabled' => [
                'title' => __('Enable/Disable'),
                'type' => 'checkbox',
                'label' => __('Enable Resurs Bank'),
                'description' => __(
                    'This enables core functions of Resurs Bank, like the payment gateway, etc. ' .
                    'When disabled, after shop functions (i.e. debiting, annulling, etc) will still work.',
                    'trbwc'
                ),
                'default' => 'yes',
            ],
            'environment' => [
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
                'description' => __(
                    'Defines if you are are live or just in test/staging. Default: test.',
                    'trbwc'
                ),
            ],
            'login' => [
                'title' => __('Resurs Bank webservices username', 'trbwc'),
                'type' => 'text',
                'description' => __(
                    'Web services username, received from Resurs Bank.',
                    'trbwc'
                ),
                'default' => '',
                'desc_tip' => true,
            ],
            'password' => [
                'title' => __('Resurs Bank webservices password', 'trbwc'),
                'type' => 'password',
                'default' => '',
                'description' => __(
                    'Web services password, received from Resurs Bank.',
                    'trbwc'
                ),
                'desc_tip' => true,
            ],
        ];

        // Extended settings - the rest of it.
        $extended = [

        ];

        $formFields = array_merge(
            $basic,
            !(bool)$getBasic ? $extended : []
        );

        return $formFields;
    }
}
