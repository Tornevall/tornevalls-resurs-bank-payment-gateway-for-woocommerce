<?php

/** @noinspection ParameterDefaultValueIsNotNullInspection */

namespace ResursBank\Module;

use Exception;
use ResursBank\Helper\WooCommerce;
use ResursBank\Helper\WordPress;
use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\Module\Network\NetWrapper;
use TorneLIB\Utils\Generic;
use WC_Logger;

/**
 * Class Data Core data class for plugin. This is where we store dynamic content without dependencies those days.
 * @package ResursBank
 * @since 0.0.1.0
 */
class Data
{
    const LOG_DEBUG = 'debug';
    const LOG_NOTICE = 'notice';
    const LOG_CRITICAL = 'critical';
    const LOG_ERROR = 'error';
    const LOG_WARNING = 'warning';

    private static $Log;

    /**
     * @var array $jsLoaders List of loadable scripts. Localizations should be named as the scripts in this list.
     * @since 0.0.1.0
     */
    private static $jsLoaders = ['resursbank_all' => 'resursbank_global.js', 'resursbank' => 'resursbank.js'];

    /**
     * @var array $jsLoadersCheckout Loadable scripts, only from checkout.
     * @since 0.0.1.0
     */
    private static $jsLoadersCheckout = ['checkout' => 'checkout.js'];

    /**
     * @var array
     * @since 0.0.1.0
     */
    private static $settingStorage = [];

    /**
     * @var array $jsLoadersAdmin List of loadable scripts for admin.
     * @since 0.0.1.0
     */
    private static $jsLoadersAdmin = [
        'resursbank_all' => 'resursbank_global.js',
        'resursbank_admin' => 'resursbank_admin.js',
    ];

    /**
     * @var Generic $genericClass
     * @since 0.0.1.0
     */
    private static $genericClass;

    /**
     * @var array $jsDependencies List of dependencies for the scripts in this plugin.
     * @since 0.0.1.0
     */
    private static $jsDependencies = ['resursbank' => ['jquery']];

    /**
     * @var array $jsDependenciesAdmin
     * @since 0.0.1.0
     */
    private static $jsDependenciesAdmin = [];

    /**
     * @var array $styles List of loadable styles.
     * @since 0.0.1.0
     */
    private static $styles = ['resursbank' => 'resursbank.css'];

    /**
     * @var array $stylesAdmin
     * @since 0.0.1.0
     */
    private static $stylesAdmin = [];

    /**
     * @var array $fileImageExtensions
     * @since 0.0.1.0
     */
    private static $fileImageExtensions = ['jpg', 'gif', 'png'];

    /**
     * @var array $formFieldDefaults
     * @since 0.0.1.0
     */
    private static $formFieldDefaults;

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
     * Returns test mode boolean.
     * @return bool
     * @since 0.0.1.0
     */
    public static function getTestMode()
    {
        return in_array(self::getResursOption('environment'), ['test', 'staging']);
    }

    /**
     * Get resurs-options from deprecated spaces (feature only returns saved data, not earlier defaults).
     * Affected namespaces:
     * woocommerce_resurs-bank_settings                // Default.
     * woocommerce_{paymentMethod}_settings            // woocommerce_resurs_bank_nr_<method>_settings.
     * woocommerce_resurs_bank_omnicheckout_settings   // Omni/RCO section.
     * wc_resurs2_salt                                 // Salts are skipped, obviously.
     * @param string $key
     * @param string $namespace
     * @return bool|mixed|null
     * @since 0.0.1.0
     */
    public static function getResursOptionDeprecated($key, $namespace = "woocommerce_resurs-bank_settings")
    {
        $return = null;

        $getOptionsNamespace = get_option($namespace);
        if (isset($getOptionsNamespace[$key])) {
            $return = $getOptionsNamespace[$key];
        }

        $trueFalse = self::getTruth($return);
        if ($trueFalse !== null) {
            $return = $trueFalse;
        }

        return $return;
    }

    /**
     * @param string $key
     * @param null $namespace
     * @return bool|string
     * @since 0.0.1.0
     */
    public static function getResursOption($key, $namespace = null)
    {
        if (preg_match('/woocom(.*?)resurs/', $namespace)) {
            return self::getResursOptionDeprecated($key, $namespace);
        }
        $optionKeyPrefix = sprintf('%s_%s', Data::getPrefix('admin'), $key);
        $return = self::getDefault($key);
        $getOptionReturn = get_option($optionKeyPrefix);

        if (!empty($getOptionReturn)) {
            $return = $getOptionReturn;
        }

        // What the old plugin never did to save space.
        if (($testBoolean = self::getTruth($return)) !== null) {
            $return = (bool)$testBoolean;
        } else {
            $return = (string)$return;
        }

        return $return;
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
     * @param $key
     * @return null
     * @since 0.0.1.0
     */
    private static function getDefault($key)
    {
        $return = '';

        if (!is_array(self::$formFieldDefaults) || !count(self::$formFieldDefaults)) {
            self::$formFieldDefaults = self::getDefaultsFromSections(FormFields::getFormFields('all'));
        }

        if (isset(self::$formFieldDefaults[$key]['default'])) {
            $return = self::$formFieldDefaults[$key]['default'];
        }

        return $return;
    }

    /**
     * @param $array
     * @return array
     * @since 0.0.1.0
     */
    private static function getDefaultsFromSections($array)
    {
        $return = [];
        foreach ($array as $section => $content) {
            $return += $content;
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

    /**
     * @return bool
     * @throws ExceptionHandler
     * @since 0.0.1.0
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
     * @since 0.0.1.0
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
     * @param bool $getBaseName
     * @return string
     * @since 0.0.1.0
     */
    public static function getPluginTitle($getBaseName = false)
    {
        return !$getBaseName ? self::getPluginDataContent('Plugin Name') : WooCommerce::getBaseName();
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
     * @param $content
     * @return mixed
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getPluginInformation($content)
    {
        $netWrapper = new NetWrapper();

        $renderData = [
            __('Plugin version', 'trbwc') => Data::getCurrentVersion(),
            __('WooCommerce', 'trbwc') => sprintf(
                __(
                    '%s, at least %s are required.',
                    'trbwc'
                ),
                WooCommerce::getWooCommerceVersion(),
                WooCommerce::getRequiredVersion()
            ),
            __('Composer version', 'trbwc') => Data::getVersionByComposer(),
            __('PHP Version', 'trbwc') => PHP_VERSION,
            __('Webservice Library', 'trbwc') => defined('ECOMPHP_VERSION') ? 'ecomphp-' . ECOMPHP_VERSION : '',
            __('Communication Library', 'trbwc') => 'netcurl-' . $netWrapper->getVersion(),
            __('Communication Drivers', 'trbwc') => implode('<br>', self::getWrapperList($netWrapper)),
        ];

        $renderData += WordPress::applyFilters('renderInformationData', $renderData);
        $content .= self::getGenericClass()->getTemplate(
            'plugin_information',
            [
                'required_drivers' => self::getSpecialString('required_drivers'),
                'support_string' => self::getSpecialString('support_string'),
                'render' => $renderData,
            ]
        );

        return $content;
    }

    /**
     * Return list of wrappers from netcurl wrapper driver.
     * @param $netWrapper
     * @return array
     * @since 0.0.1.0
     */
    private static function getWrapperList($netWrapper)
    {
        $wrapperList = [];
        foreach ($netWrapper->getWrappers() as $wrapperClass => $wrapperInstance) {
            $wrapperList[] = preg_replace('/(.*)\\\\(.*?)$/', '$2', $wrapperClass);
        }

        return $wrapperList;
    }

    /**
     * @return Generic
     * @since 0.0.1.0
     */
    public static function getGenericClass()
    {
        if (self::$genericClass !== Generic::class) {
            self::$genericClass = new Generic();
            self::$genericClass->setTemplatePath(Data::getGatewayPath('templates'));
        }

        return self::$genericClass;
    }

    /**
     * Return long translations.
     * @param $key
     * @return mixed
     * @since 0.0.1.0
     */
    private static function getSpecialString($key)
    {
        $array = [
            'required_drivers' => __(
                'If something is wrong and you are unsure of where to begin, take a look at the communication ' .
                'drivers. Wrappers that must be available for this plugin to fully work, is either the ' .
                'CurlWrapper or SimpleStreamWrapper -and- the SoapClientWrapper. Resurs Bank offers ' .
                'multiple services over both Soap/XML and REST so they have to be present.',
                'trbwc'
            ),
            'support_string' => __(
                'If you ever need support with this plugin, you should primarily check this ' .
                'page before sending support requests. When you send the requests, make sure you do ' .
                'include the information below in your message. Doing this, it will be easier ' .
                'in the end to help you out.',
                'trbwc'
            ),
        ];

        return isset($array[$key]) ? $array[$key] : '';
    }

    /**
     * Filter based addon.
     * Do not use getResursOption in this request as this may cause infinite loops.
     * @param $currentArray
     * @param $section
     * @return array
     * @since 0.0.1.0
     */
    public static function getDependentSettings($currentArray, $section)
    {
        $return = $currentArray;

        $developerArray = [
            'developer' => [
                'title' => __('Developer Settings', 'trbwc'),
                'plugin_section' => [
                    'type' => 'title',
                    'title' => 'Plugin Settings',
                ],
                'priorVersionsDisabled' => [
                    'id' => 'priorVersionsDisabled',
                    'title' => __('Disable RB 2.x', 'trbwc'),
                    'type' => 'checkbox',
                    'desc' => __(
                        'Disable prior similar versions of the Resurs Bank plugin (v2.x-series) - ' .
                        'You might need an extra reload after save',
                        'trbwc'
                    ),
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
            ],
        ];

        if ($section === 'all' || self::getShowDeveloper()) {
            $return += $developerArray;
        }

        return $return;
    }

    /**
     * @return bool
     * @since 0.0.1.0
     */
    public static function getShowDeveloper()
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

    /**
     * @param $severity
     * @param $logMessage
     * @param array $context
     * @since 0.0.1.0
     */
    public static function setLogInternal($severity, $logMessage, $context = [])
    {
        if (empty(self::$Log)) {
            self::$Log = new WC_Logger();
        }

        $prefix = sprintf('rbwc_%s', $severity);
        $message = sprintf('%s message: %s', $prefix, $logMessage);

        switch ($severity) {
            case 'debug':
                self::$Log->debug($message, $context);
                break;
            case 'critical':
                self::$Log->critical($message, $context);
                break;
            case 'error':
                self::$Log->error($message, $context);
                break;
            case 'warning':
                self::$Log->warning($message, $context);
                break;
            default:
                self::$Log->notice($message, $context);
                break;
        }
    }
}
