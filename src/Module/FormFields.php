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
     * @param string $section
     * @param string $id
     * @return array
     */
    public static function getFormFields($section = 'basic', $id = '')
    {
        if (empty($section)) {
            $section = 'basic';
        }

        // Basic settings. Returned to ResursDefault configuration.
        $formFields = [
            'basic' => [
                'enabled' => [
                    'id' => 'enabled',
                    'title' => __('Enable/Disable', 'trbwc'),
                    'type' => 'checkbox',
                    'label' => __('Enable Resurs Bank', 'trbwc'),
                    'description' => __(
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
                    'description' => __(
                        'Defines if you are are live or just in test/staging. Default: test.',
                        'trbwc'
                    ),
                ],
                'login' => [
                    'id' => 'login',
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
                    'id' => 'password',
                    'title' => __('Resurs Bank webservices password', 'trbwc'),
                    'type' => 'password',
                    'default' => '',
                    'description' => __(
                        'Web services password, received from Resurs Bank.',
                        'trbwc'
                    ),
                    'desc_tip' => true,
                ],
            ],
        ];

        if ($section === 'all') {
            $return = $formFields;
        } else {
            $return = isset($formFields[$section]) ? self::getTransformedIdArray($formFields[$section], $id) : [];
        }

        return $return;
    }

    /**
     * @return string
     */
    public static function getTransformedIdArray($array, $add)
    {
        $return = $array;

        if (!empty($add)) {
            foreach ($array as $itemKey => $item) {
                if (isset($item['id'])) {
                    $item['id'] = sprintf('%s_%s', $add, $item['id']);
                }
                $return[$itemKey] = $item;
            }
        }

        return $return;
    }
}
