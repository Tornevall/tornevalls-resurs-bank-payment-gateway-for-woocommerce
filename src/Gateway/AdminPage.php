<?php

namespace ResursBank\Gateway;

use ResursBank\Helper\WordPress;
use ResursBank\Module\Data;
use ResursBank\Module\FormFields;
use TorneLIB\Utils\Generic;
use WC_Admin_Settings;
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
        $this->id = Data::getPrefix('default');

        $this->generic = new Generic();
        $this->generic->setTemplatePath(Data::getGatewayPath('templates'));

        // In case we need it in future.
        $this->label_image = sprintf(
            '<img src="%s" border="0">',
            Data::getImage('logo2018.png')
        );

        add_filter('woocommerce_settings_tabs_array', [$this, 'add_settings_page'], 20);
        add_action('woocommerce_settings_' . $this->id, [$this, 'output']);
        add_action('woocommerce_sections_' . $this->id, [$this, 'output_sections']);
        add_action('woocommerce_settings_save_' . $this->id, [$this, 'save']);

        //add_action('woocommerce_update_options_' . $this->id, [$this, 'resurs_bank_settings_save_legacy']);

        parent::__construct();
    }

    /**
     * @param $content
     * @param $current_section
     * @return mixed
     * @since 0.0.1.0
     */
    public static function getAdminDynamicContent($content, $current_section)
    {
        return $content;
    }

    /**
     * @since 0.0.1.0
     */
    public static function getId()
    {
        return Data::getPrefix('default');
    }

    public function save()
    {
        global $current_section;
        $settings = $this->get_settings($current_section);
        woocommerce_update_options($settings);
    }

    /**
     * @param string $current_section
     * @return array
     */
    public function get_settings($current_section = '')
    {
        $settings = FormFields::getFormFields($current_section);

        return apply_filters('woocommerce_get_settings_' . $this->id, $settings, $current_section);
    }

    /**
     * @return array|mixed|void
     * @since 0.0.1.0
     */
    public function get_sections()
    {
        return apply_filters('woocommerce_get_sections_' . $this->id, $this->getSectionNames());
    }

    /**
     * @return array
     */
    private function getSectionNames()
    {
        return [
            '' => __('Plugin and account settings', 'trbwc'),
            'advanced' => __('Advanced', 'trbwc'),
        ];
    }

    /**
     * @since 0.0.1.0
     */
    public function output()
    {
        global $current_section;
        $sectionNames = $this->getSectionNames();

        $settings = $this->get_settings($current_section);
        // This generates data for the form fields.
        ob_start();
        WC_Admin_Settings::output_fields($settings);
        $outputHtml = ob_get_clean();

        // This displays the entire configuration.
        echo $this->generic->getTemplate(
            'adminpage_main',
            [
                'adminPageTop' => sprintf(
                    __(
                        'Extended configuration view for Resurs Bank: %s',
                        'trbwc'
                    ),
                    $sectionNames[$current_section]
                ),
                'adminPageSectionHtml' => $outputHtml,
                'adminDynamicContent' => WordPress::applyFilters('adminDynamicContent', '', $current_section, 'musli'),
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
}
