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
     * @var array
     */
    private static $settingStorage = [];
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
     * AdminPage constructor.
     * @since 0.0.1.0
     */
    public function __construct()
    {
        $this->id = Data::getPrefix('admin');

        // In case we need it in future.
        $this->label_image = sprintf(
            '<img src="%s" border="0">',
            Data::getImage('logo2018.png')
        );

        add_filter('woocommerce_settings_tabs_array', [$this, 'add_settings_page'], 20);
        add_action('woocommerce_settings_' . $this->id, [$this, 'output']);
        add_action('woocommerce_sections_' . $this->id, [$this, 'output_sections']);
        add_action('woocommerce_settings_save_' . $this->id, [$this, 'save']);

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
        if ($current_section === 'information') {
            $content .= WordPress::applyFilters('getPluginInformation', null);
        }
        return $content;
    }

    /**
     * @since 0.0.1.0
     */
    public static function getId()
    {
        return Data::getPrefix('admin');
    }

    /**
     * Filter based addon.
     * Do not use getResursOption in this request as this may cause infinite loops.
     * @param $currentArray
     * @return array
     * @since 0.0.1.0
     */
    public static function getDependentSettings($currentArray)
    {
        $return = $currentArray;

        $developerArray = [
            'developer' => [
                'title' => __('Developer Settings', 'trbwc'),
                'plugin_section' => [
                    'type' => 'title',
                    'title' => 'Plugin Settings',
                ],
                'getPriorVersionsDisabled' => [
                    'id' => 'getPriorVersionsDisabled',
                    'title' => __('Disable RB 2.x', 'trbwc'),
                    'type' => 'checkbox',
                    'desc' => __('Disable prior similar versions of the Resurs Bank plugin (v2.x-series).', 'trbwc'),
                    'desc_top' => __(
                        'This setting will disable, not entirely, but the functions in Resurs Bank Gateway v2.x ' .
                        'with help from filters in that release.',
                        'trbwc'
                    ),
                    'default' => 'yes',
                ],
                'dev_section_end' => [
                    'type' => 'sectionend',
                ],
                'testing_section' => [
                    'type' => 'title',
                    'title' => 'Test Section',
                ],
                'version_exceed' => [
                    'title' => 'Exceed version requirement',
                    'type' => 'checkbox',
                    'desc' => 'Set required version to 999.0.0',
                    'desc_tip' => __(
                        'Figure out what happens when WooCommerce version does not meet the requirements.',
                        'trbwc'
                    ),
                ],
            ],
        ];

        if (self::getShowDeveloper()) {
            $return += $developerArray;
        }

        return $return;
    }

    /**
     * @return bool
     * @since 0.0.1.0
     */
    private static function getShowDeveloper()
    {
        if (!isset(self::$settingStorage['showDeveloper'])) {
            self::$settingStorage['showDeveloper'] = Data::getTruth(
                get_option(
                    sprintf('%s_%s', Data::getPrefix('admin'), 'show_developer')
                )
            );
        }

        return (bool)self::$settingStorage['showDeveloper'];
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
        $settings = FormFields::getFormFields($current_section, $this->id);

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
        $return = [];
        $formFields = Data::getFormFields('all');
        foreach ($formFields as $sectionKey => $sectionArray) {
            if ($sectionKey === 'basic') {
                $return[''] = isset($sectionArray['title']) ?
                    $sectionArray['title'] : __('Plugin and account settings', 'trbwc');
            } else {
                $return[$sectionKey] = isset($sectionArray['title']) ? $sectionArray['title'] : $sectionKey;
            }
        }

        return $return;
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
        echo Data::getGenericClass()->getTemplate(
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
