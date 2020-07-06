<?php

namespace ResursBank\Gateway;

use ResursBank\Module\Data;
use TorneLIB\Utils\Generic;

/**
 * Class ResursDefault
 * @package Resursbank\Gateway
 */
class ResursDefault extends \WC_Payment_Gateway
{
    /**
     * @var Generic $generic Generic library, mainly used for automatically handling templates.
     * @since 0.0.1.0
     */
    private $generic;

    public function __construct()
    {
        $this->generic = new Generic();
        $this->generic->setTemplatePath(Data::getGatewayPath('templates'));

        $this->id = ResursDefault::class;
        $this->method_title = __('Resurs Bank', 'trbwc');
        $this->method_description = __('Resurs Bank Payment Gateway with dynamic payment methods.', 'trbwc');
        $this->title = __('Resurs Bank AB', 'trbwc');
        //$this->has_fields = true;
        //$this->icon = Data::getImage('logo2018.png');
        $this->init_settings();
        $this->init_form_fields();
    }

    /**
     * @since 0.0.1.0
     */
    public function init_form_fields()
    {
        $this->form_fields = Data::getFormFields(true);
    }

    /**
     * @since 0.0.1.0
     */
    public function admin_options()
    {
        parent::admin_options();
        echo $this->generic->getTemplate(
            'adminpage_default_adminoptions',
            [
                'moreSettingsHref' => admin_url('admin.php?page=wc-settings&tab=rbwc_gateway'),
                'moreSettings' => __('Looking for more settings?', 'trbwc'),
                'moreSettingsDescription' => __(
                    'This plugin offers a lot of settings, which has been traditionally been kept in a separate tab. ' .
                    'The page you are currently visiting only contains the vital settings for the plugin.',
                    'trbwc'
                ),
                'moreSettingsAwareness' => __(
                    'You should also be aware of that this switch does not enable nor disable the entire plugin. ' .
                    'Just the basic functionality for it.',
                    'trbwc'
                ),
                'moreSettingsHrefText' => __(
                    'You can reach the other settings here!',
                    'trbwc'
                ),
            ]
        );
    }

    public function process_admin_options()
    {
        return parent::process_admin_options();
    }
}