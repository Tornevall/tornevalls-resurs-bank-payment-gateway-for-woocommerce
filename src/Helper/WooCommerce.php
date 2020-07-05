<?php

namespace ResursBank\Helper;

use ResursBank\Gateway\AdminPage;

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
}
