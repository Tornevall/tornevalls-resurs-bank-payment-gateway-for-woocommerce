<?php

namespace ResursBank\Gateway;

use ResursBank\Module\Data;
use TorneLIB\Utils\Generic;
use WC_Settings_Page;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Settings
 * @package Resursbank\Gateway
 */
class AdminPage extends WC_Settings_Page
{
    /**
     * @var string $id
     */
    protected $id = 'rbwc_gateway';

    /**
     * @var string $label
     */
    protected $label = 'Resurs Bank';

    /**
     * @var string $label_image
     */
    protected $label_image;

    /**
     * @var bool $parentConstructor
     */
    private $parentConstructor = false;

    /**
     * @var Generic $generic Generic library, mainly used for automatically handling templates.
     */
    private $generic;

    public function __construct()
    {
        $this->generic = new Generic();
        $this->generic->setTemplatePath(Data::getGatewayPath('templates'));

        // In case we need it in future.
        $this->label_image = sprintf(
            '<img src="%s" border="0">',
            Data::getImage('logo2018.png')
        );

        add_action('woocommerce_settings_' . $this->id, [$this, 'getResursSettingsView']);
        add_action('woocommerce_sections_' . $this->id, [$this, 'getOutputSections']);
        //add_action('woocommerce_update_options_' . $this->id, [$this, 'resurs_bank_settings_save_legacy']);

        parent::__construct();
    }

    /**
     * @since 0.0.1.0
     */
    public function getResursSettingsView()
    {
        echo $this->generic->getTemplate(
            'adminpage_main.html',
            [
                'adminPageTop' => __('Extended configuration view for Resurs Bank.', 'trbwc'),
            ]
        );
    }

    /**
     * @since 0.0.1.0
     * @deprecated No longer working.
     */
    public function getSettingTabs()
    {
        global $current_tab;
        if (!$this->parentConstructor) {
            printf(
                '<a href="%s" class="nav-tab %s">%s</a>',
                esc_html(admin_url('admin.php?page=wc-settings&tab=' . $this->id)),
                ($current_tab === $this->id ? 'nav-tab-active' : ''),
                $this->label_image
            );
        }
    }

    public function getOutputSections()
    {

    }
}
