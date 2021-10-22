<?php

namespace ResursBank\Module;

use Exception;
use ResursBank\Gateway\ResursDefault;
use ResursBank\Helpers\WooCommerce;
use ResursBank\Helpers\WordPress;
use ResursException;
use RuntimeException;
use stdClass;
use TorneLIB\Data\Aes;
use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\IO\Data\Strings;
use TorneLIB\Module\Network\NetWrapper;
use TorneLIB\Utils\Generic;
use WC_Customer;
use WC_Logger;
use WC_Order;
use function count;
use function is_array;

/**
 * Class Data Core data class for plugin. This is where we store dynamic content without dependencies those days.
 *
 * @package ResursBank
 * @since 0.0.1.0
 */
class Data
{
    /**
     * @var string
     * @since 0.0.1.0
     */
    const LOG_INFO = 'info';
    /**
     * @var string
     * @since 0.0.1.0
     */
    const LOG_DEBUG = 'debug';
    /**
     * @var string
     * @since 0.0.1.0
     */
    const LOG_NOTICE = 'notice';
    /**
     * @var string
     * @since 0.0.1.0
     */
    const LOG_CRITICAL = 'critical';
    /**
     * @var string
     * @since 0.0.1.0
     */
    const LOG_ERROR = 'error';
    /**
     * @var string
     * @since 0.0.1.0
     */
    const LOG_WARNING = 'warning';
    /**
     * @var string
     * @since 0.0.1.0
     */
    const CAN_LOG_JUNK = 'junk';

    /**
     * @var string
     * @since 0.0.1.0
     */
    const CAN_LOG_ORDER_EVENTS = 'order_events';

    /**
     * @var string
     * @since 0.0.1.0
     */
    const CAN_LOG_ORDER_DEVELOPER = 'order_developer';

    /**
     * @var array
     * @since 0.0.1.0
     */
    private static $can = [];

    /**
     * @var array Order metadata to search, to find Resurs Order References.
     * @since 0.0.1.0
     */
    private static $searchArray = [
        'resursReference',
        'resursDefaultReference',
        'paymentId',
        'paymentIdLast',
    ];
    /**
     * @var WC_Logger $Log
     * @since 0.0.1.0
     */
    private static $Log;

    /**
     * @var array $payments
     * @since 0.0.1.0
     */
    private static $payments = [];

    /**
     * @var array $jsLoaders List of loadable scripts. Localizations should be named as the scripts in this list.
     * @since 0.0.1.0
     */
    private static $jsLoaders = [
        'resursbank_all' => 'resursbank_global.js',
        'resursbank' => 'resursbank.js',
    ];

    /**
     * @var array $jsLoadersCheckout Loadable scripts, only from checkout.
     * @since 0.0.1.0
     */
    private static $jsLoadersCheckout = [
        'resursbank_checkout' => 'resursbank_checkout.js',
        'resursbank_rco_v1' => 'resursbank_rco_v1.js',
        'resursbank_rco_v2' => 'resursbank_rco_v2.js',
    ];

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
    private static $jsDependencies = [
        'resursbank' => ['jquery'],
        'resursbank_rco_v1' => ['jquery'],
        'resursbank_rco_v2' => ['jquery'],
    ];

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
    private static $stylesAdmin = ['resursbank_admin' => 'resursbank_admin.css'];

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
     * @var Aes
     * @since 0.0.1.0
     */
    private static $encrypt;

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
     *
     * @param string $subDirectory
     * @return string
     * @version 0.0.1.0
     */
    public static function getGatewayPath($subDirectory = null)
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
    private static function getImageUrl($imageFileName = null)
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
     *
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
     * @since 0.0.1.0
     */
    public static function getAnnuityFactors()
    {
        global $product;

        if (is_object($product) && !empty(self::getResursOption('currentAnnuityFactor'))) {
            self::getAnnuityHtml(
                wc_get_price_to_display($product),
                self::getResursOption('currentAnnuityFactor'),
                (int)self::getResursOption('currentAnnuityDuration')
            );
        }
    }

    /**
     * @param string $key
     * @param null $namespace
     * @param bool $getDefaults
     * @return bool|string
     * @since 0.0.1.0
     */
    public static function getResursOption($key, $namespace = null, $getDefaults = true)
    {
        $return = null;
        if (preg_match('/woocom(.*?)resurs/', $namespace)) {
            return self::getResursOptionDeprecated($key, $namespace);
        }
        $optionKeyPrefix = sprintf('%s_%s', self::getPrefix('admin'), $key);
        if ($getDefaults) {
            // RUn defaultchecker once.
            $return = self::getDefault($key);
        }
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
     * Get resurs-options from deprecated spaces (feature only returns saved data, not earlier defaults).
     * Affected namespaces:
     * woocommerce_resurs-bank_settings                // Default.
     * woocommerce_{paymentMethod}_settings            // woocommerce_resurs_bank_nr_<method>_settings.
     * woocommerce_resurs_bank_omnicheckout_settings   // Omni/RCO section.
     * wc_resurs2_salt                                 // Salts are skipped, obviously.
     *
     * @param string $key
     * @param string $namespace
     * @return bool|mixed|null
     * @since 0.0.1.0
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    public static function getResursOptionDeprecated($key, $namespace = 'woocommerce_resurs-bank_settings')
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
     * Anti collider.
     *
     * @param null $extra
     * @return string
     * @since 0.0.1.0
     */
    public static function getPrefix($extra = null)
    {
        if (empty($extra)) {
            return RESURSBANK_PREFIX;
        }

        return RESURSBANK_PREFIX . '_' . $extra;
    }

    /**
     * Initialize default data from formFields.
     *
     * @since 0.0.1.0
     */
    public static function getDefaultsInit()
    {
        if (!is_array(self::$formFieldDefaults) || !count(self::$formFieldDefaults)) {
            self::$formFieldDefaults = self::getDefaultsFromSections(FormFields::getFormFields('all'));
        }

        return self::$formFieldDefaults;
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
     * @param $key
     * @return null
     * @since 0.0.1.0
     */
    private static function getDefault($key)
    {
        $return = '';

        if (isset(self::$formFieldDefaults[$key]['default'])) {
            $return = self::$formFieldDefaults[$key]['default'];
        }

        return $return;
    }

    /**
     * @param $wcDisplayPrice
     * @param $annuityMethod
     * @param $annuityDuration
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function getAnnuityHtml($wcDisplayPrice, $annuityMethod, $annuityDuration)
    {
        $customerCountry = self::getCustomerCountry();
        switch ($customerCountry) {
            case 'FI':
                $minimumPaymentLimit = WordPress::applyFilters('getMinimumAnnuityPrice', 15, $customerCountry);
                break;
            default:
                $minimumPaymentLimit = WordPress::applyFilters('getMinimumAnnuityPrice', 150, $customerCountry);
        }

        $monthlyPrice = Api::getResurs()->getAnnuityPriceByDuration($wcDisplayPrice, $annuityMethod, $annuityDuration);
        if ($monthlyPrice >= $minimumPaymentLimit || self::getTestMode()) {
            $partPayString = self::getPartPayStringByTags(
                WordPress::applyFilters(
                    'partPaymentString',
                    sprintf(
                        __('Part pay from %s per month.', 'trbwc'),
                        self::getWcPriceSpan($monthlyPrice)
                    )
                ),
                [
                    'monthlyPrice' => $monthlyPrice,
                    'monthlyDuration' => $annuityDuration,
                    'paymentLimit' => $minimumPaymentLimit,
                    'isTest' => self::getTestMode(),
                ]
            );
            $annuityTemplate = Data::getGenericClass()->getTemplate(
                'product_annuity.phtml',
                [
                    'monthlyPrice' => $monthlyPrice,
                    'monthlyDuration' => $annuityDuration,
                    'partPayString' => $partPayString,
                    'isTest' => self::getTestMode(),
                ]
            );

            echo $annuityTemplate;
        }
    }

    /**
     * @return false|mixed|void
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getCustomerCountry()
    {
        global $woocommerce;
        /** @var WC_Customer $wcCustomer */
        $wcCustomer = $woocommerce->customer;

        $return = null;
        if (!empty($wcCustomer)) {
            $woocommerceCustomerCountry = $wcCustomer->get_billing_country();
            $return = !empty($woocommerceCustomerCountry) ?
                $woocommerceCustomerCountry : get_option('woocommerce_default_country');
        }

        return $return;
    }

    /**
     * Returns test mode boolean.
     *
     * @return bool
     * @since 0.0.1.0
     */
    public static function getTestMode()
    {
        return in_array(self::getResursOption('environment'), ['test', 'staging']);
    }

    /**
     * @param $content
     * @since 0.0.1.0
     */
    private static function getPartPayStringByTags($content, $data)
    {
        $tags = self::getCompatibleTags(
            [
                'monthlyPrice' => $data['monthlyPrice'],
                'monthlyDuration' => $data['monthlyDuration'],
            ],
            $data
        );
        $replaceTags = [];
        $replaceWith = [];
        foreach ($tags as $tagKey => $tagValue) {
            $replaceTags[] = sprintf('/\[%s\]/i', $tagKey);
            $replaceWith[] = $tagValue;
        }

        return preg_replace(
            $replaceTags,
            $replaceWith,
            $content
        );
    }

    /**
     * @param $replaceTags
     * @param $data
     * @return array
     * @since 0.0.1.0
     */
    private static function getCompatibleTags($replaceTags, $data)
    {
        $v2 = [
            'payFromAnnuity' => wc_price($data['monthlyPrice']),
            'payFrom' => $data['monthlyPrice'],
            'paymentLimit' => $data['paymentLimit'],
            'annuityDuration' => $data['monthlyDuration'],
            'costOfPurchase' => null,
            'defaultAnnuityString' => null,
            'annuityFactors' => null,
        ];

        return array_merge($replaceTags, $v2);
    }

    /**
     * @param $monthlyPrice
     * @return string
     * @since 0.0.1.0
     */
    private static function getWcPriceSpan($monthlyPrice)
    {
        return sprintf('<span id="r_annuity_price">%s</span>', wc_price($monthlyPrice));
    }

    /**
     * @return Generic
     * @since 0.0.1.0
     */
    public static function getGenericClass()
    {
        if (self::$genericClass !== Generic::class) {
            self::$genericClass = new Generic();
            self::$genericClass->setTemplatePath(self::getGatewayPath('templates'));
        }

        return self::$genericClass;
    }

    /**
     * @return bool
     * @since 0.0.1.0
     */
    public static function canMock($specificMock)
    {
        $return = false;
        if (self::isTest() && (bool)Data::getResursOption('allow_mocking', null, false)) {
            $mockOptionName = Strings::returnSnakeCase(sprintf('mock%s', ucfirst($specificMock)));
            if (Data::getResursOption(
                $mockOptionName,
                null,
                false
            )) {
                // Disable mockoption after first execution.
                Data::setResursOption($mockOptionName, false);
                return true;
            }
        }

        return $return;
    }

    /**
     * @return bool
     * @since 0.0.1.0
     */
    public static function isTest()
    {
        return (self::getResursOption('environment', null, false) === 'test');
    }

    /**
     * @param $key
     * @param $value
     * @return bool
     * @since 0.0.1.0
     */
    public static function setResursOption($key, $value)
    {
        return update_option(sprintf('%s_%s', self::getPrefix('admin'), $key), $value);
    }

    /**
     * @return bool
     * @since 0.0.1.0
     */
    public static function hasDefaults()
    {
        return is_array(self::$formFieldDefaults) && count(self::$formFieldDefaults);
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
    public static function getPluginScripts($isAdmin = null)
    {
        if ($isAdmin) {
            $return = self::$jsLoadersAdmin;
        } else {
            $return = array_merge(
                self::$jsLoaders,
                is_checkout() ? WordPress::applyFilters('jsLoadersCheckout', self::$jsLoadersCheckout) : []
            );
        }

        return $return;
    }

    /**
     * @param bool $isAdmin
     * @return array
     * @since 0.0.1.0
     */
    public static function getPluginStyles($isAdmin = null)
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
     * @return string
     * @since 0.0.1.0
     */
    public static function getPaymentMethodBySession()
    {
        return WooCommerce::getSessionValue('paymentMethod');
    }

    /**
     * Get the name of current checkout in use.
     * @return string
     * @since 0.0.1.0
     */
    public static function getCheckoutType()
    {
        $currentCheckoutType = Data::getResursOption('checkout_type');
        // Warning: Filter makes this plugin feel very bad.
        if ($currentCheckoutType === ResursDefault::TYPE_RCO &&
            (bool)WordPress::applyFiltersDeprecated('temporary_disable_checkout', null)
        ) {
            $currentCheckoutType = ResursDefault::TYPE_SIMPLIFIED;
        }
        switch ($currentCheckoutType) {
            case 'simplified':
                $return = ResursDefault::TYPE_SIMPLIFIED;
                break;
            case 'hosted':
                $return = ResursDefault::TYPE_HOSTED;
                break;
            case 'rco':
                $return = ResursDefault::TYPE_RCO;
                break;
            default:
                $return = '';
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
     *
     * @return string
     * @since 0.0.1.0
     */
    public static function getCurrentVersion()
    {
        return self::getPluginDataContent('version');
    }

    /**
     * Get data from plugin setup (top of init.php).
     *
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
     *
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
    public static function getPluginTitle($getBaseName = null)
    {
        return !$getBaseName ? self::getPluginDataContent('Plugin Name') : WooCommerce::getBaseName();
    }

    /**
     * @param bool $getBasic
     * @return array
     * @since 0.0.1.0
     */
    public static function getFormFields($getBasic = null)
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
            __('Plugin version', 'trbwc') => self::getCurrentVersion(),
            __('WooCommerce', 'trbwc') => sprintf(
                __(
                    '%s, at least %s are required.',
                    'trbwc'
                ),
                WooCommerce::getWooCommerceVersion(),
                WooCommerce::getRequiredVersion()
            ),
            __('Composer version', 'trbwc') => self::getVersionByComposer(),
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
     *
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
     * Return long translations.
     *
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
     * @return bool
     * @since 0.0.1.0
     */
    public static function isProductionAvailable()
    {
        return (
            !empty(Data::getResursOption('login_production')) &&
            !empty(Data::getResursOption('password_production'))
        );
    }

    /**
     * @param $data
     * @return string
     * @since 0.0.1.0
     */
    public static function setEncryptData($data)
    {
        try {
            $crypt = self::getCrypt();
            $return = $crypt->aesEncrypt($data);
            $dataEncryptionState = null;
        } catch (Exception $e) {
            $return = (new Strings())->base64urlEncode($data);
            $dataEncryptionState = $e;
        }

        if (!empty($e)) {
            Data::setLogNotice(
                sprintf(
                    __('%s failed encryption (%d): %s. Failover to base64.', 'trbwc'),
                    __FUNCTION__,
                    $e->getCode(),
                    $e->getMessage()
                )
            );
        }

        return (string)$return;
    }

    /**
     * @return Aes
     * @throws ExceptionHandler
     * @since 0.0.1.0
     */
    public static function getCrypt()
    {
        if (empty(self::$encrypt)) {
            $aesKey = self::getResursOption('key');
            $aesIv = self::getResursOption('iv');

            if (empty($aesKey) && empty($aesIv)) {
                $aesKey = uniqid('k_' . microtime(true), true);
                $aesIv = uniqid('i_' . microtime(true), true);
                self::setResursOption('key', $aesKey);
                self::setResursOption('iv', $aesIv);
            }

            self::$encrypt = new Aes();
            self::$encrypt->setAesKeys(
                self::getPrefix() . $aesKey,
                self::getPrefix() . $aesIv
            );
        }

        return self::$encrypt;
    }

    /**
     * @param $logMessage
     * @since 0.0.1.0
     */
    public static function setLogNotice($logMessage)
    {
        self::setLogInternal(
            self::LOG_NOTICE,
            $logMessage
        );
    }

    /**
     * @param $severity
     * @param $logMessage
     * @since 0.0.1.0
     */
    public static function setLogInternal($severity, $logMessage)
    {
        if (empty(self::$Log)) {
            self::$Log = new WC_Logger();
        }

        $prefix = sprintf('%s_%s', self::getPrefix(), $severity);

        $from = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Console';
        $message = sprintf('%s (%s): %s', $prefix, $from, $logMessage);

        switch ($severity) {
            case 'info':
                self::$Log->info($message);
                break;
            case 'debug':
                self::$Log->debug($message);
                break;
            case 'critical':
                self::$Log->critical($message);
                break;
            case 'error':
                self::$Log->error($message);
                break;
            case 'warning':
                self::$Log->warning($message);
                break;
            default:
                self::$Log->notice($message);
                break;
        }
    }

    /**
     * @param $data
     * @param bool $base64
     * @return mixed
     * @since 0.0.1.0
     */
    public static function getDecryptData($data, $base64 = false)
    {
        try {
            if (!$base64) {
                $crypt = self::getCrypt();
                $return = $crypt->aesDecrypt($data);
            } else {
                $return = (new Strings())->base64urlDecode($data);
            }
        } catch (Exception $e) {
            $return = (new Strings())->base64urlDecode($data);
        }

        return $return;
    }

    /**
     * If plugin is enabled on admin level.
     * @return bool
     * @since 0.0.1.0
     */
    public static function isEnabled()
    {
        return Data::getResursOption('enabled', null, false);
    }

    /**
     * @param $fromFunction
     * @param $message
     * @return bool
     * @since 0.0.1.0
     */
    public static function setDeveloperLog($fromFunction, $message)
    {
        return self::canLog(
            self::CAN_LOG_ORDER_DEVELOPER,
            sprintf(
                __(
                    'DevLog Method "%s" message: %s.',
                    'trbwc'
                ),
                $fromFunction,
                $message
            )
        );
    }

    /**
     * @param $eventType
     * @param $logData
     * @return bool
     * @since 0.0.1.0
     */
    public static function canLog($eventType, $logData)
    {
        $return = false;

        // Ask the data base once, then get local storage value.
        if (!isset(self::$can[$eventType])) {
            self::$can[$eventType] = (bool)self::getResursOption(
                sprintf('can_log_%s', $eventType)
            );
        }

        if (self::$can[$eventType]) {
            $return = true;
            self::setLogInfo($logData);
        }

        return $return;
    }

    /**
     * @param $logMessage
     * @since 0.0.1.0
     */
    public static function setLogInfo($logMessage)
    {
        self::setLogInternal(
            self::LOG_INFO,
            $logMessage
        );
    }

    /**
     * @param $key
     * @param $order
     * @return mixed|null
     * @throws ResursException
     * @since 0.0.1.0
     */
    public static function getOrderMeta($key, $order)
    {
        $return = null;
        if (is_array($order) && isset($order['order'])) {
            // Get from a prefetched request.
            $orderData = $order;
        } else {
            $orderData = self::getOrderInfo($order);
        }

        if (isset($orderData['meta'][$key])) {
            $return = $orderData['meta'][$key];
        }
        $pluginPrefixedKey = sprintf('%s_%s', self::getPrefix(), $key);
        if (isset($orderData['meta'])) {
            $return = self::getOrderMetaByKey($pluginPrefixedKey, $orderData['meta']);
        }
        if ($key === 'resurspayment' && isset($orderData['ecom']) && is_object($orderData['ecom'])) {
            $return = $orderData['ecom'];
        }

        return $return;
    }

    /**
     * Advanced order fetching. Make sure you use Data::canHandleOrder($paymentMethod) before running this.
     * It is not our purpose to interfere with all orders.
     *
     * @param mixed $order
     * @param null $orderIsResursReference
     * @return array
     * @throws ResursException
     * @since 0.0.1.0
     */
    public static function getOrderInfo($order, $orderIsResursReference = null)
    {
        $return = [];
        $orderId = null;
        if (is_object($order)) {
            $orderId = $order->get_id();
        } elseif ((int)$order && !is_string($order) && !$orderIsResursReference) {
            $orderId = $order;
            $order = new WC_Order($orderId);
        } elseif (is_string($order)) {
            // Landing here it might be a Resurs or EComPHP reference.
            if (($foundOrderId = self::getOrderByEcomRef($order))) {
                $order = self::getOrderInfo($foundOrderId);
                $orderId = $order['order']->get_id();
            }
        }

        if ((int)($orderId) &&
            is_object($order)
        ) {
            // Dynamically fetch order data during order-view session (sharable over many actions).
            $return = self::getPrefetchedPayment($orderId);

            if (!count($return)) {
                $return = self::setPrefetchedPayment($orderId, $order);
            }
        }

        return $return;
    }

    /**
     * @param $orderReference
     * @param null $asOrder
     * @return null
     * @since 0.0.1.0
     */
    public static function getOrderByEcomRef($orderReference, $asOrder = null)
    {
        $return = 0;

        foreach (self::$searchArray as $key) {
            $getPostId = (int)self::getRefVarFromMeta($key, $orderReference);
            if ($getPostId) {
                $return = $getPostId;
                break;
            }
        }

        if ($return && (bool)$asOrder) {
            $return = new WC_Order($return);
        }

        return $return;
    }

    /**
     * @param $key
     * @param $reference
     * @return int
     * @since 0.0.1.0
     */
    private static function getRefVarFromMeta($key, $reference)
    {
        $getPostId = self::getRefVarFromDatabase($key, $reference);
        if (!$getPostId) {
            $getPostId = self::getRefVarFromDatabase(
                sprintf(
                    '%s_%s',
                    self::getPrefix(),
                    $key
                ),
                $reference
            );
        }
        if ((int)$getPostId) {
            $return = (int)$getPostId;
        }

        return isset($return) ? $return : 0;
    }

    /**
     * @param $key
     * @param $reference
     * @return string|null
     * @since 0.0.1.0
     * @noinspection SqlResolve
     * @noinspection UnknownInspectionInspection
     */
    private static function getRefVarFromDatabase($key, $reference)
    {
        global $wpdb;
        $tableName = $wpdb->prefix . 'postmeta';
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT `post_id` FROM {$tableName} WHERE `meta_key` = '%s' and `meta_value` = '%s'",
                $key,
                $reference
            )
        );
    }

    /**
     * Get locally stored payment if it is present.
     *
     * @param $key
     * @return array
     * @since 0.0.1.0
     */
    public static function getPrefetchedPayment($key)
    {
        return isset(self::$payments[$key]) ? self::$payments[$key] : [];
    }

    /**
     * Set and return order information.
     *
     * @param $orderId
     * @param WC_Order $order
     * @return array|mixed
     * @since 0.0.1.0
     */
    private static function setPrefetchedPayment($orderId, $order)
    {
        $return['order'] = $order;
        $return['meta'] = (int)$orderId ? get_post_custom($orderId) : [];
        $return['resurs'] = self::getResursReference($return);
        $return['resurs_secondary'] = self::getResursReference($return, ['resursDefaultReference']);

        if (!empty($return['resurs'])) {
            $return = self::getPreparedDataByEcom($return);
            self::getLocalizedOrderData($return);
        }
        // Store payment for later use.
        self::$payments[$orderId] = $return;

        return $return;
    }

    /**
     * @param $orderDataArray
     * @param string $searchFor
     * @return string
     */
    public static function getResursReference($orderDataArray, $searchFor = '')
    {
        $return = '';

        $searchUsing = !empty($searchFor) && is_array($searchFor) ? $searchFor : self::$searchArray;
        if (isset($orderDataArray['meta']) && is_array($orderDataArray)) {
            foreach ($searchUsing as $searchKey) {
                $protectedMetaKey = sprintf('%s_%s', self::getPrefix(), $searchKey);
                if (isset($orderDataArray['meta'][$searchKey])) {
                    $return = array_pop($orderDataArray['meta'][$searchKey]);
                    break;
                }
                if (isset($orderDataArray['meta'][$protectedMetaKey])) {
                    $return = array_pop($orderDataArray['meta'][$protectedMetaKey]);
                    break;
                }
            }
        }

        return $return;
    }

    /**
     * Fetch order info from EComPHP.
     *
     * @param $return
     * @return mixed
     * @return array
     * @since 0.0.1.0
     */
    public static function getPreparedDataByEcom($return)
    {
        $return = self::getOrderInfoExceptionData($return);
        try {
            if (!$return['ecomException']['code']) {
                try {
                    $return['ecom'] = Api::getPayment($return['resurs'], null, $return);
                    $return['ecom_had_reference_problems'] = false;
                } catch (Exception $e) {
                    if (!empty($return['resurs_secondary'])) {
                        $return['ecom'] = Api::getPayment($return['resurs_secondary'], null, $return);
                    }
                    $return['ecom_had_reference_problems'] = true;
                }
                $return = WooCommerce::getFormattedPaymentData($return);
                $return = WooCommerce::getPaymentInfoDetails($return);
            }
        } catch (Exception $e) {
            self::canLog(
                self::CAN_LOG_ORDER_EVENTS,
                sprintf('%s exception (%s), %s.', __FUNCTION__, $e->getCode(), $e->getMessage())
            );
            self::setLogException($e);
            $return['ecomException'] = [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ];
        }
        return (array)$return;
    }

    /**
     * Prepare for exceptions.
     *
     * @param $return
     * @return mixed
     * @since 0.0.1.0
     */
    private static function getOrderInfoExceptionData($return)
    {
        $return['errorString'] = __(
            'An error occurred during the payment information retrieval from Resurs Bank so we can ' .
            'not show the current order status for the moment.',
            'trbwc'
        );
        $return['ecomException'] = [
            'message' => null,
            'code' => 0,
        ];

        return $return;
    }

    /**
     * @param Exception $exception
     * @since 0.0.1.0
     */
    public static function setLogException($exception)
    {
        if (!isset($_SESSION[self::getPrefix()])) {
            $_SESSION[self::getPrefix()]['exception'] = [];
        }
        $_SESSION[self::getPrefix()]['exception'][] = $exception;
        self::setLogError(
            sprintf(
                __(
                    '%s internal generic exception %s: %s',
                    'trbwc'
                ),
                self::getPrefix(),
                $exception->getCode(),
                $exception->getMessage()
            )
        );
    }

    /**
     * @param $logMessage
     * @since 0.0.1.0
     */
    public static function setLogError($logMessage)
    {
        self::setLogInternal(
            self::LOG_ERROR,
            $logMessage
        );
    }

    /**
     * @param array $orderData
     * @since 0.0.1.0
     */
    private static function getLocalizedOrderData($orderData = null)
    {
        $localizeArray = [
            'resursOrder' => isset($orderData['resurs']) ? $orderData['resurs'] : '',
            'dynamicLoad' => self::getResursOption('dynamicOrderAdmin'),
        ];

        $scriptName = sprintf('%s_resursbank_order', self::getPrefix());
        WordPress::setEnqueue(
            $scriptName,
            'resursbank_order.js',
            is_admin(),
            $localizeArray
        );
    }

    /**
     * @param $suffixedKey
     * @param $orderDataMeta
     * @return mixed|null
     * @since 0.0.1.0
     */
    private static function getOrderMetaByKey($suffixedKey, $orderDataMeta)
    {
        $return = null;
        if (is_array($orderDataMeta)) {
            foreach (['', 'u_'] as $orderMetaKey) {
                $currentMetaKey = sprintf('%s%s', $orderMetaKey, $suffixedKey);
                if (isset($orderDataMeta[$currentMetaKey])) {
                    $handleResult = $orderDataMeta[$currentMetaKey];
                    if (is_array($handleResult)) {
                        $return = array_pop($handleResult);
                    } else {
                        $return = $handleResult;
                    }
                }
            }
        }
        return $return;
    }

    /**
     * @return bool
     * @since 0.0.1.0
     */
    public static function hasOldGateway()
    {
        return defined('RB_WOO_VERSION') ? true : false;
    }

    /**
     * @param $paymentMethodId
     * @return array|stdClass
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getPaymentMethodById($paymentMethodId)
    {
        $return = [];

        $storedMethods = Api::getPaymentMethods(true);
        if (is_array($storedMethods)) {
            foreach ($storedMethods as $method) {
                if (isset($method->id) && $method->id === $paymentMethodId) {
                    $return = $method;
                    break;
                }
            }
        }

        return $return;
    }

    /**
     * Makes sure nothing interfering with orders that has not been created by us. If this returns false,
     * it means we should not be there and touch things.
     *
     * @param $thisMethod
     * @return bool
     * @since 0.0.1.0
     */
    public static function canHandleOrder($thisMethod)
    {
        $return = false;

        $allowMethod = [
            'resurs_bank_',
            'rbwc_',
            'trbwc_',
        ];

        $isResursDeprecated = false;
        foreach ($allowMethod as $methodKey) {
            if ((bool)preg_match(sprintf('/^%s/', $methodKey), $thisMethod)) {
                if ((bool)preg_match('/^resurs_bank_/', $methodKey)) {
                    $isResursDeprecated = true;
                    break;
                }
                $return = true;
                break;
            }
        }

        if ($isResursDeprecated && Data::getResursOption('deprecated_interference')) {
            $return = true;
        }

        return $return;
    }

    /**
     * @param WC_Order|int $order Order or orderId
     * @param string $key
     * @param string $value
     * @param bool $protected Set to false if you want the metadata to be stored as unprotected data.
     * @param bool $insert Update metadata if already exists (false). Add more metadata if already exists (true).
     * @return bool|int
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    public static function setOrderMeta($order, $key, $value, $protected = true, $insert = false)
    {
        if (method_exists($order, 'get_id')) {
            $orderId = $order->get_id();
        } elseif ((int)$order > 0) {
            $orderId = $order;
        }

        if (isset($orderId)) {
            self::canLog(
                self::CAN_LOG_JUNK,
                sprintf(
                    '%s (%s): %s=%s (protected=%s).',
                    __FUNCTION__,
                    $orderId,
                    $key,
                    $value,
                    ($protected ? 'true' : 'false')
                )
            );
            if ($insert) {
                $return = add_post_meta(
                    $orderId,
                    sprintf('%s_%s', (bool)$protected ? self::getPrefix() : 'u_' . self::getPrefix(), $key),
                    $value
                );
            } else {
                $return = update_post_meta(
                    $orderId,
                    sprintf('%s_%s', (bool)$protected ? self::getPrefix() : 'u_' . self::getPrefix(), $key),
                    $value
                );
            }
        } else {
            throw new RuntimeException(
                sprintf(
                    __(
                        'Unable to update order meta in %s - object $order is of wrong type or not an integer.',
                        'trbwc'
                    ),
                    __FUNCTION__
                ),
                400
            );
        }

        return $return;
    }

    /**
     * @param array $settings
     * @return array
     * @since 0.0.1.0
     */
    public static function getGeneralSettings($settings = null)
    {
        foreach ((array)$settings as $setting) {
            if (isset($setting['id']) && $setting['id'] === 'woocommerce_price_num_decimals') {
                $currentPriceDecimals = wc_get_price_decimals();
                if ($currentPriceDecimals < 2 && self::getResursOption('prevent_rounding_panic')) {
                    $settings[] = [
                        'title' => __('Number of decimals headsup message', 'trbwc'),
                        'type' => 'decimal_warning',
                        'desc' => 'Description',
                    ];
                }
            }
        }
        return (array)$settings;
    }

    /**
     * @param $currentValue
     * @return mixed
     * @since 0.0.1.0
     */
    public static function getDecimalValue($currentValue)
    {
        global $current_tab;
        if ($current_tab !== 'general' && $currentValue < 2 && self::getResursOption('prevent_rounding_panic')) {
            $currentValue = 2;
        }
        return $currentValue;
    }

    /**
     * @return bool
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function isGetAddressSupported()
    {
        return in_array(self::getCustomerCountry(), ['NO', 'SE'], true);
    }

    /**
     * @return string
     * @since 0.0.1.0
     */
    public static function getErrorNotices()
    {
        $wcNotices = wc_get_notices();
        $internalErrorMessage = '';
        if (isset($wcNotices['error']) && count($wcNotices['error'])) {
            $wcErrorCollection = [];
            foreach ($wcNotices['error'] as $arr) {
                $wcErrorCollection[] = $arr['notice'];
            }
            $internalErrorMessage = implode("<br>\n", $wcErrorCollection);
            Data::canLog(
                Data::LOG_ERROR,
                $internalErrorMessage
            );
        }
        return $internalErrorMessage;
    }

    /**
     * @return string
     * @since 0.0.1.0
     */
    public static function getCustomerType()
    {
        self::setCustomerTypeToSession();
        return self::getCustomerTypeFromSession();
    }

    /**
     * @since 0.0.1.0
     */
    private static function setCustomerTypeToSession()
    {
        $customerTypeByGetAddress = WooCommerce::getRequest('resursSsnCustomerType', true);

        if (!empty($customerTypeByGetAddress)) {
            WooCommerce::setSessionValue('resursSsnCustomerType', $customerTypeByGetAddress);
        }
    }

    /**
     * @return array|mixed|string|null
     * @since 0.0.1.0
     */
    private static function getCustomerTypeFromSession()
    {
        $return = WooCommerce::getSessionValue('resursSsnCustomerType');
        $customerTypeByCompanyName = WooCommerce::getRequest('billing_company', true);

        return empty($customerTypeByCompanyName) ? $return : 'LEGAL';
    }

    /**
     * @return bool
     * @since 0.0.1.0
     */
    public static function clearCredentialNotice()
    {
        return self::delResursOption('front_credential_error');
    }

    /**
     * Remove self settings from option.
     * @param $key
     * @return bool
     */
    public static function delResursOption($key)
    {
        return delete_option(sprintf('%s_%s', self::getPrefix('admin'), $key));
    }

    /**
     * @return bool
     * @since 0.0.1.0
     */
    public static function getCredentialNotice()
    {
        return Data::setResursOption(
            'front_credential_error',
            json_encode(
                [
                    'code' => 401,
                    'message' => __(
                        'Received an error message from Resurs Bank that indicates that you credentials are incorrect.',
                        'trbwc'
                    ),
                ]
            )
        );
    }

    /**
     * @param $paymentMethod
     * @return bool
     * @since 0.0.1.0
     */
    public static function isResursMethod($paymentMethod)
    {
        return (bool)preg_match(sprintf('/^%s_/', self::getPrefix()), $paymentMethod);
    }
}
