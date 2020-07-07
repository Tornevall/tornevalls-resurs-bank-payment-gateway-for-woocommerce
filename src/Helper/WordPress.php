<?php

namespace ResursBank\Helper;

use Exception;
use ResursBank\Module\Data;
use TorneLIB\IO\Data\Strings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WordPress WordPress related actions.
 * @package ResursBank
 * @since 0.0.1.0
 */
class WordPress
{
    /**
     * @since 0.0.1.0
     */
    public static function initializePlugin()
    {
        // Do not actively work where WooCommerce isn't live.
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }
        self::setupFilters();
        self::setupActions();
        self::setupScripts();
    }

    /**
     * Internal filter setup.
     * @since 0.0.1.0
     */
    private static function setupFilters()
    {
        add_filter('plugin_action_links', 'ResursBank\Helper\WooCommerce::getPluginAdminUrl', 10, 2);
        add_filter('woocommerce_get_settings_pages', 'ResursBank\Helper\WooCommerce::getSettingsPages');
        add_filter('woocommerce_payment_gateways', 'ResursBank\Helper\WooCommerce::getGateway');
        add_filter('rbwc_admin_dynamic_content', 'ResursBank\Gateway\AdminPage::getAdminDynamicContent', 10, 2);
        add_filter('rbwc_get_dependent_settings', 'ResursBank\Gateway\AdminPage::getDependentSettings');
        add_filter('rbwc_get_plugin_information', 'ResursBank\Module\Data::getPluginInformation');
    }

    /**
     * @since 0.0.1.0
     */
    private static function setupActions()
    {
        add_action('admin_notices', 'ResursBank\Helper\WordPress::getAdminNotices');
    }

    /**
     * @since 0.0.1.0
     */
    private static function setupScripts()
    {
        add_action('wp_enqueue_scripts', 'ResursBank\Helper\WordPress::setResursBankScripts');
        add_action('admin_enqueue_scripts', 'ResursBank\Helper\WordPress::setResursBankScriptsAdmin');
    }

    /**
     * @since 0.0.1.0
     */
    public static function getAdminNotices()
    {
        global $current_tab, $parent_file;

        try {
            if ($current_tab === Data::getPrefix('admin') || $parent_file === 'woocommerce') {
                WooCommerce::testRequiredVersion();
            }
        } catch (Exception $e) {
            echo Data::getGenericClass()->getTemplate(
                'adminpage_woocommerce_requirement',
                [
                    'requiredVersion' => WooCommerce::getRequiredVersion(),
                    'currentVersion' => WooCommerce::getWooCommerceVersion(),
                ]
            );
        }
    }

    /**
     * @return bool
     * @since 0.0.1.0
     * @noinspection PhpExpressionResultUnusedInspection
     */
    public static function getPriorVersionsDisabled()
    {
        return Data::getResursOption('getPriorVersionsDisabled');
    }

    /**
     * @since 0.0.1.0
     */
    public static function setResursBankScriptsAdmin()
    {
        self::setResursBankScripts(true);
    }

    /**
     * @param bool $isAdmin
     * @since 0.0.1.0
     */
    public static function setResursBankScripts($isAdmin = null)
    {
        foreach (Data::getPluginStyles($isAdmin) as $styleName => $styleFile) {
            wp_enqueue_style(
                sprintf('%s-%s', Data::getPrefix(), $styleName),
                sprintf(
                    '%s/css/%s?%s',
                    Data::getGatewayUrl(),
                    $styleFile,
                    Data::getTestMode() ? time() : 'static'
                ),
                [],
                Data::getCurrentVersion()
            );
        }

        foreach (Data::getPluginScripts($isAdmin) as $scriptName => $scriptFile) {
            wp_enqueue_script(
                sprintf('%s-%s', Data::getPrefix(), $scriptName),
                sprintf(
                    '%s/js/%s?%s',
                    Data::getGatewayUrl(),
                    $scriptFile,
                    Data::getTestMode() ? Data::getPrefix() . '-' . time() : 'static'
                ),
                Data::getJsDependencies($scriptName, $isAdmin)
            );
        }

    }

    /**
     * @param $filterName
     * @param $value
     * @return mixed
     * @since 0.0.1.0
     */
    public static function applyFilters($filterName, $value)
    {
        $applyArray = [
            sprintf(
                '%s_%s',
                'rbwc',
                self::getFilterName($filterName)
            ),
            $value,
        ];

        $applyArray = array_merge($applyArray, self::getFilterArgs(func_get_args()));

        return call_user_func_array(
            'apply_filters',
            $applyArray
        );
    }

    /**
     * @param $filterName
     * @return string
     * @since 0.0.1.0
     */
    private static function getFilterName($filterName)
    {
        $return = $filterName;
        if (defined('RESURSBANK_SNAKECASE_FILTERS')) {
            $return = (new Strings())->getSnakeCase($filterName);
        }

        return $return;
    }

    /**
     * Clean up arguments and return the real ones.
     * @param $args
     * @return array
     * @since 0.0.1.0
     */
    private static function getFilterArgs($args)
    {
        if (is_array($args) && count($args) > 2) {
            array_shift($args);
            array_shift($args);
        }

        return $args;
    }

    /**
     * @param $filterName
     * @param $value
     * @return mixed|void
     * @since 0.0.1.0
     * @deprecated Marked deprecated, use the new definitions instead.
     */
    public static function applyFiltersDeprecated($filterName, $value)
    {
        return apply_filters(
            sprintf('%s_%s', 'resurs_bank', self::getFilterName($filterName)),
            $value,
            self::getFilterArgs(func_get_args())
        );
    }
}
