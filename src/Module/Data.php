<?php

/** @noinspection ParameterDefaultValueIsNotNullInspection */

namespace ResursBank\Module;

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
    private static $jsLoaders = ['resursbank_all' => 'resurs_global.js', 'resursbank' => 'resursbank.js'];

    /** @var array $jsLoadersCheckout Loadable scripts, only from checkout. */
    private static $jsLoadersCheckout = ['checkout' => 'checkout.js'];

    /** @var array $jsLoadersAdmin List of loadable scripts for admin. */
    private static $jsLoadersAdmin = [
        'resursbank_all' => 'resurs_global.js',
        'resursbank_admin' => 'resursbank_admin.js',
    ];

    /** @var array $jsDependencies List of dependencies for the scripts in this plugin. */
    private static $jsDependencies = ['resursbank' => ['jquery']];

    /** @var array $jsDependenciesAdmin */
    private static $jsDependenciesAdmin = [];

    /** @var array $styles List of loadable styles. */
    private static $styles = ['resursbank' => 'resursbank.css'];

    /** @var array $stylesAdmin */
    private static $stylesAdmin = [];

    /** @var array $fileImageExtensions */
    private static $fileImageExtensions = ['jpg', 'gif', 'png'];

    /**
     * @param $imageName
     * @return string
     * @since 0.0.1.0
     */
    public static function getImage($imageName)
    {
        $imageFileName = null;
        $imageFile = sprintf(
            '%s/%s',
            self::getGatewayPath('images'),
            $imageName
        );

        // Match allowed file extensions and return if it exists within the file name.
        if ((bool)preg_match(
            sprintf('/^(.*?)(.%s)$/', implode('|.', self::$fileImageExtensions)),
            $imageFile
        )) {
            $imageFile = preg_replace(
                sprintf('/^(.*)(.%s)$/', implode('|.', self::$fileImageExtensions)),
                '$1',
                $imageFile
            );
        } else {
            return null;
        }

        foreach (self::$fileImageExtensions as $extension) {
            if (file_exists($imageFile . '.' . $extension)) {
                $imageFileName = $imageFile . '.' . $extension;
            }
        }

        return $imageFileName !== null ? self::getImageUrl($imageName) : null;
    }

    /**
     * Get file path for major initializer (init.php).
     * @param string $subDirectory
     * @return string
     * @version 0.0.1.0
     */
    public static function getGatewayPath($subDirectory = '')
    {
        $subPathTest = preg_replace('/\//', '', $subDirectory);
        $gatewayPath = preg_replace('/\/+$/', '', RESURSBANK_GATEWAY_PATH);

        if (!empty($subPathTest) && file_exists($gatewayPath . '/' . $subPathTest)) {
            $gatewayPath .= '/' . $subPathTest;
        }

        return $gatewayPath;
    }

    /**
     * @param string $imageFileName
     * @return string
     * @version 0.0.1.0
     */
    private static function getImageUrl($imageFileName = '')
    {
        $return = sprintf(
            '%s/images',
            self::getGatewayUrl()
        );

        if (!empty($imageFileName)) {
            $return .= '/' . $imageFileName;
        }

        return $return;
    }

    /**
     * @return string
     * @version 0.0.1.0
     */
    public static function getGatewayUrl()
    {
        return preg_replace('/\/+$/', '', plugin_dir_url(self::getPluginInitFile()));
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
     * @param bool $isAdmin
     * @return array
     * @version 0.0.1.0
     */
    public static function getPluginScripts($isAdmin = false)
    {
        if ($isAdmin) {
            $return = self::$jsLoadersAdmin;
        } else {
            $return = array_merge(
                self::$jsLoaders,
                is_checkout() ? self::$jsLoadersCheckout : []
            );
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
        if ($isAdmin) {
            $return = self::$stylesAdmin;
        } else {
            $return = self::$styles;
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
        if ($isAdmin) {
            $return = isset(self::$jsDependenciesAdmin[$scriptName]) ? self::$jsDependenciesAdmin[$scriptName] : [];
        } else {
            $return = isset(self::$jsDependencies[$scriptName]) ? self::$jsDependencies[$scriptName] : [];
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
        return true;
    }

    /**
     * Anti collider.
     * @param string $extra
     * @return string
     * @since 0.0.1.0
     */
    public static function getPrefix($extra = '')
    {
        if (empty($extra)) {
            return RESURSBANK_PREFIX;
        }

        return RESURSBANK_PREFIX . '_' . $extra;
    }

    /**
     * @return bool
     * @throws ExceptionHandler
     */
    public static function getValidatedVersion()
    {
        $return = false;
        if (version_compare(self::getCurrentVersion(), self::getVersionByComposer(), '==')) {
            $return = true;
        }
        return $return;
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
     * Fetch plugin version from composer package.
     * @return string
     * @throws ExceptionHandler
     * @version 0.0.1.0
     */
    public static function getVersionByComposer()
    {
        return (new Generic())->getVersionByComposer(
            self::getGatewayPath() . '/composer.json'
        );
    }

    /**
     * @param bool $getBasic
     * @return array
     * @since 0.0.1.0
     */
    public static function getFormFields($getBasic = false)
    {
        return FormFields::getFormFields($getBasic);
    }

    /**
     * @param string $key
     * @param string $option_name
     * @return bool|string
     * @since 0.0.1.0
     */
    public static function getResursOption($key = '', $option_name = 'woocommerce_rbwc_gateway_settings')
    {
        $return = self::getDefault($key);
        $getOptionsNamespace = get_option($option_name);
        if (isset($getOptionsNamespace[$key])) {
            $return = $getOptionsNamespace[$key];
        }

        // What the old plugin never did to save space.
        if (self::getTruth($return) !== null) {
            $return = (bool)$return;
        } else {
            $return = (string)$return;
        }

        return $return;
    }

    /**
     * @param $key
     * @return null
     * @since 0.0.1.0
     */
    private static function getDefault($key)
    {
        $return = '';

        if (!count(self::$formFieldDefaults)) {
            self::$formFieldDefaults = FormFields::getFormFields();
        }

        if (isset(self::$formFieldDefaults[$key]['default'])) {
            $return = self::$formFieldDefaults[$key]['default'];
        }

        return $return;
    }

    /**
     * @param $value
     * @return bool|null
     * @since 0.0.1.0
     */
    public static function getTruth($value)
    {
        if (in_array($value, ['true', 'yes'])) {
            $return = true;
        } elseif (in_array($value, ['false', 'no'])) {
            $return = false;
        } else {
            $return = null;
        }

        return $return;
    }
}
