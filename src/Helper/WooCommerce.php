<?php

namespace ResursBank\Helper;

use Exception;
use ResursBank\Gateway\AdminPage;
use ResursBank\Gateway\ResursDefault;
use ResursBank\Module\Data;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WooCommerce WooCommerce related actions.
 * @package ResursBank
 * @since 0.0.1.0
 */
class WooCommerce
{
    /**
     * @var $basename
     * @since 0.0.1.0
     */
    private static $basename;

    /**
     * By this plugin lowest required woocommerce version.
     * @var string
     * @since 0.0.1.0
     */
    private static $requiredVersion = '3.4.0';

    /**
     * @return bool
     * @since 0.0.1.0
     */
    public static function getActiveState()
    {
        return in_array(
            'woocommerce/woocommerce.php',
            apply_filters('active_plugins', get_option('active_plugins')),
            true
        );
    }

    /**
     * @param $settings
     * @return mixed
     * @since 0.0.1.0
     */
    public static function getSettingsPages($settings)
    {
        if (is_admin()) {
            $settings[] = new AdminPage();
        }

        return $settings;
    }

    /**
     * @param $gateways
     * @return mixed
     * @since 0.0.1.0
     */
    public static function getGateway($gateways)
    {
        $gateways[] = ResursDefault::class;
        return $gateways;
    }

    /**
     * Self aware setup link.
     * @param $links
     * @param $file
     * @return mixed
     */
    public static function getPluginAdminUrl($links, $file)
    {
        if (empty(self::$basename)) {
            self::$basename = trim(plugin_basename(Data::getGatewayPath()));
        }
        if (strpos($file, self::$basename) !== false) {
            $links[] = sprintf(
                '<a href="%s?page=wc-settings&tab=%s">%s</a>',
                admin_url(),
                Data::getPrefix('admin'),
                __(
                    'Settings'
                )
            );
        }
        return $links;
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function testRequiredVersion()
    {
        if (version_compare(self::getWooCommerceVersion(), self::$requiredVersion, '<')) {
            throw new Exception(
                'Your WooCommerce release are too old. Please upgrade.',
                500
            );
        }
    }

    /**
     * @return string
     * @since 0.0.1.0
     */
    public static function getWooCommerceVersion()
    {
        global $woocommerce;

        $return = null;

        if (isset($woocommerce)) {
            $return = $woocommerce->version;
        }

        return $return;
    }

    /**
     * @return string
     * @since 0.0.1.0
     */
    public static function getRequiredVersion()
    {
        return self::$requiredVersion;
    }
}
