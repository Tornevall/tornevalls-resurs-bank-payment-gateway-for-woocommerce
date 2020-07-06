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

        $this->id = Data::getPrefix('default');
        $this->method_title = __('Resurs Bank', 'trbwc');
        $this->method_description = __('Resurs Bank Payment Gateway with dynamic payment methods.', 'trbwc');
        $this->title = __('Resurs Bank AB', 'trbwc');
        //$this->has_fields = true;
        //$this->icon = Data::getImage('logo2018.png');
        $this->init_settings();
        $this->init_form_fields();

        add_action('woocommerce_update_options', [$this, 'process_admin_options']);
    }

    /**
     * @since 0.0.1.0
     */
    public function init_form_fields()
    {
        $this->form_fields = Data::getFormFields();
    }

    /**
     * @since 0.0.1.0
     */
    public function admin_options()
    {
        ob_start();
        parent::admin_options();
        $adminDefaultForm = ob_get_clean();

        echo $this->generic->getTemplate(
            'adminpage_default_adminoptions',
            [
                'adminDefaultForm' => $adminDefaultForm,
                'moreSettingsHref' => admin_url(sprintf("admin.php?page=wc-settings&tab=%s", AdminPage::getId())),
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
}
