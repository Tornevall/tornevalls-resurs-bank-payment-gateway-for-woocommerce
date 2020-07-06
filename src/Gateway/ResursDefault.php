<?php

namespace ResursBank\Gateway;

use ResursBank\Module\Data;

/**
 * Class ResursDefault
 * @package Resursbank\Gateway
 */
class ResursDefault extends \WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'rbwc_gateway';
        $this->method_title = __('Resurs Bank', 'trbwc');
        $this->method_description = __('Resurs Bank Payment Gateway with dynamic payment methods.', 'trbwc');
        $this->title = __('Resurs Bank AB', 'trbwc');
        $this->has_fields = true;
        $this->icon = Data::getImage('logo2018.png');
        $this->init_settings();
        $this->init_form_fields();
    }

    /**
     * @since 0.0.1.0
     */
    public function init_form_fields()
    {
        $this->form_fields = [
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
        ];
    }

    /**
     * @since 0.0.1.0
     */
    public function admin_options()
    {
    }
}