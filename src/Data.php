<?php

namespace ResursBank;

use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\Utils\Generic;

/**
 * Class Data Core data class for plugin. This is where we store dynamic content without dependencies those days.
 * @package ResursBank
 * @since 0.0.1.0
 */
class Data
{
    /** @var array $jsLoaders List of loadable scripts. Localizations should be named as the scripts in this list. */
    private static $jsLoaders = ['resursbank' => 'resursbank.js'];

    /** @var array $jsLoadersAdmin List of loadable scripts for admin. */
    private static $jsLoadersAdmin = ['resursbank' => 'resursbank.js'];

    /** @var array $jsDependencies List of dependencies for the scripts in this plugin. */
    private static $jsDependencies = ['resursbank' => ['jquery']];

    /** @var array $jsDependenciesAdmin */
    private static $jsDependenciesAdmin = [];

    /** @var array $styles List of loadable styles. */
    private static $styles = ['resursbank' => 'resursbank.css'];

    /** @var array $stylesAdmin */
    private static $stylesAdmin = [];

    /**
     * @return string
     * @version 0.0.1.0
     */
    public static function getGatewayUrl()
    {
        return preg_replace('/\/+$/', '', plugin_dir_url(__FILE__));
    }

    /**
     * @return string
     * @version 0.0.1.0
     */
    public static function getGatewayBackend()
    {
        return sprintf(
            '%s?action=resurs_bank_backend',
            admin_url('admin-ajax.php')
        );
    }

    /**
     * Fetch plugin version from composer package.
     * @return string
     * @throws ExceptionHandler
     * @version 0.0.1.0
     */
    public static function getVersionByComposer()
    {
        return (new Generic())->getVersionByComposer(
            self::getGatewayPath()
        );
    }

    /**
     * Get file path for major initializer (init.php).
     * @return string
     * @version 0.0.1.0
     */
    public static function getGatewayPath()
    {
        return preg_replace('/\/+$/', '', RESURSBANK_GATEWAY_PATH);
    }

    /**
     * Get current version from plugin data.
     * @return string
     * @version 0.0.1.0
     */
    public static function getCurrentVersion()
    {
        return self::getPluginDataContent('version');
    }

    /**
     * Get data from plugin setup (top of init.php).
     * @param $key
     * @return string
     * @version 0.0.1.0
     */
    private static function getPluginDataContent($key)
    {
        $pluginContent = get_file_data(self::getPluginInitFile(), [$key => $key]);
        return $pluginContent[$key];
    }

    /**
     * Get waypoint for init.php.
     * @return string
     * @version 0.0.1.0
     */
    private static function getPluginInitFile()
    {
        return sprintf(
            '%s/init.php',
            self::getGatewayPath()
        );
    }

    /**
     * @param bool $isAdmin
     * @return array
     * @version 0.0.1.0
     */
    public static function getPluginScripts($isAdmin = false)
    {
        if (!$isAdmin) {
            $return = self::$jsLoaders;
        } else {
            $return = self::$jsLoadersAdmin;
        }

        return $return;
    }

    /**
     * @param bool $isAdmin
     * @return array
     * @since 0.0.1.0
     */
    public static function getPluginStyles($isAdmin = false)
    {
        if (!$isAdmin) {
            $return = self::$styles;
        } else {
            $return = self::$stylesAdmin;
        }

        return $return;
    }

    /**
     * @param $scriptName
     * @param $isAdmin
     * @return array
     * @since 0.0.1.0
     */
    public static function getJsDependencies($scriptName, $isAdmin)
    {
        if (!$isAdmin) {
            $return = isset(self::$jsDependencies) ? self::$jsDependencies[$scriptName] : [];
        } else {
            $return = isset(self::$jsDependenciesAdmin) ? self::$jsDependenciesAdmin[$scriptName] : [];
        }

        return $return;
    }

    /**
     * @return bool
     * @since 0.0.1.0
     */
    public static function getDeveloperMode()
    {
        $return = false;

        if (defined('RESURSBANK_IS_DEVELOPER')) {
            $return = RESURSBANK_IS_DEVELOPER;
        }

        return $return;
    }

    /**
     * Returns test mode boolean.
     * @return bool
     * @since 0.0.1.0
     * @todo Fetch this mode from database.
     */
    public static function getTestMode()
    {
        /** @todo Change to false when database is ready. */
        $return = true;

        return $return;
    }

    /**
     * Anti collider.
     * @return string
     * @since 0.0.1.0
     */
    public static function getPrefix()
    {
        return RESURSBANK_PREFIX;
    }
}
