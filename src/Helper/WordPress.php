<?php

namespace ResursBank\Helper;

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
        add_filter('woocommerce_get_settings_pages', 'ResursBank\Helper\WooCommerce::getSettingsPages');
        add_filter('woocommerce_payment_gateways', 'ResursBank\Helper\WooCommerce::getGateway');
    }

    /**
     * @since 0.0.1.0
     */
    private static function setupActions()
    {
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
     * @return bool
     * @since 0.0.1.0
     */
    public static function getPriorVersionsDisabled()
    {
        return true;
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
                    isResursTest() ? time() : 'static'
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
                    isResursTest() ? Data::getPrefix() . '-' . time() : 'static'
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
        return apply_filters(
            sprintf('%s_%s', 'rbwc', self::getFilterName($filterName)),
            $value,
            self::getFilterArgs(func_get_args())
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
