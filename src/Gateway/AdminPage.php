<?php

namespace ResursBank\Gateway;

use Exception;
use ResursBank\Helpers\WordPress;
use ResursBank\Module\Data;
use ResursBank\Module\FormFields;
use WC_Admin_Settings;
use WC_Settings_Page;

/**
 * Class Settings
 * @package Resursbank\Gateway
 */
class AdminPage extends WC_Settings_Page
{
    /**
     * @var string $label_image
     * @since 0.0.1.0
     */
    protected $label_image;

    /**
     * @var bool $parentConstructor
     * @since 0.0.1.0
     */
    private $parentConstructor = false;

    /**
     * AdminPage constructor.
     * @since 0.0.1.0
     */
    public function __construct()
    {
        $this->id = Data::getPrefix('admin');
        $this->label = 'Resurs Bank';

        // In case we need it in future.
        /** @noinspection HtmlUnknownTarget */
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
     * @since 0.0.1.0
     */
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
        return apply_filters('woocommerce_get_sections_' . $this->id, $this->getSectionNames(__FUNCTION__));
    }

    /**
     * @param null $fromFunction
     * @return array
     * @since 0.0.1.0
     */
    private function getSectionNames($fromFunction = null)
    {
        $return = [];
        $formFields = Data::getFormFields('all');

        if ($fromFunction === 'get_sections' && !FormFields::getShowDeveloper()) {
            unset($formFields['developer']);
        }

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
     * @throws Exception
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
                'adminDynamicContent' => WordPress::applyFilters('adminDynamicContent', '', $current_section),
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
            /** @noinspection HtmlUnknownTarget */
            printf(
                '<a href="%s" class="nav-tab %s">%s</a>',
                esc_html(admin_url('admin.php?page=wc-settings&tab=' . $this->id)),
                ($current_tab === $this->id ? 'nav-tab-active' : ''),
                $this->label_image
            );
        }
    }
}
