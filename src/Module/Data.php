<?php
/** @noinspection EfferentObjectCouplingInspection */
/** @noinspection SpellCheckingInspection */
/** @noinspection ParameterDefaultValueIsNotNullInspection */

namespace ResursBank\Module;

use Exception;
use ResursBank\Gateway\ResursDefault;
use Resursbank\RBEcomPHP\ResursBank;
use ResursBank\Service\WooCommerce;
use ResursBank\Service\WordPress;
use ResursException;
use RuntimeException;
use stdClass;
use TorneLIB\Data\Aes;
use TorneLIB\Exception\Constants;
use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\IO\Data\Arrays;
use TorneLIB\IO\Data\Strings;
use TorneLIB\Module\Network\NetWrapper;
use TorneLIB\Utils\Generic;
use WC_Customer;
use WC_Logger;
use WC_Order;
use function count;
use function defined;
use function in_array;
use function is_array;
use function is_object;
use function is_string;

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
     * Can log all backend calls.
     * var string
     * @since 0.0.1.0
     */
    const CAN_LOG_BACKEND = 'backend';

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
     * @var string
     * @since 0.0.1.0
     */
    const CAN_LOG_INFO = 'info';

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
    public static function getGatewayPath($subDirectory = null): string
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
    private static function getImageUrl($imageFileName = null): string
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
    public static function getGatewayUrl(): string
    {
        return preg_replace('/\/+$/', '', plugin_dir_url(self::getPluginInitFile()));
    }

    /**
     * Get waypoint for init.php.
     *
     * @return string
     * @version 0.0.1.0
     */
    private static function getPluginInitFile(): string
    {
        return sprintf(
            '%s/init.php',
            self::getGatewayPath()
        );
    }

    /**
     * @return int
     * @since 0.0.1.0
     */
    public static function getTimeoutStatus(): int
    {
        return (int)get_transient(
            sprintf('%s_resurs_api_timeout', self::getPrefix())
        );
    }

    /**
     * @param ResursBank $resursConnection
     * @param Exception $exception
     * @return bool
     * @since 0.0.1.0
     */
    public static function setTimeoutStatus($resursConnection, $exception = null): bool
    {
        $return = false;
        $timeoutByException = false;

        if ($exception instanceof Exception && !$resursConnection->hasTimeoutException()) {
            $exceptionCode = $exception->getCode();
            if ($exceptionCode === 28 || $exceptionCode === Constants::LIB_NETCURL_SOAP_TIMEOUT) {
                $timeoutByException = true;
            }
        }

        // If positive values are sent here, we store a timestamp for 60 seconds with a transient entry.
        if ($timeoutByException || $resursConnection->hasTimeoutException()) {
            $return = set_transient(
                sprintf('%s_resurs_api_timeout', self::getPrefix()),
                time(),
                60
            );
        }

        return $return;
    }

    /**
     * @param null $forceTimeout
     * @return int
     * @since 0.0.1.0
     */
    public static function getDefaultApiTimeout($forceTimeout = null): int
    {
        $useDefault = $forceTimeout === null ? 10 : (int)$forceTimeout;
        $currentTimeout = (int)WordPress::applyFilters('setCurlTimeout', $useDefault);
        return ($currentTimeout > 0 ? $currentTimeout : $useDefault);
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getAnnuityFactors()
    {
        global $product;

        $currentFactor = self::getResursOption('currentAnnuityFactor');
        if (is_object($product) && !empty($currentFactor)) {
            try {
                self::getAnnuityHtml(
                    wc_get_price_to_display($product),
                    self::getResursOption('currentAnnuityFactor'),
                    (int)self::getResursOption('currentAnnuityDuration')
                );
            } catch (Exception $annuityException) {
                $resursApi = ResursBankAPI::getResurs();
                self::setTimeoutStatus($resursApi, $annuityException);
                if ($resursApi->hasTimeoutException()) {
                    echo Data::getEscapedHtml(
                        sprintf(
                            '<div class="annuityTimeout">%s</div>',
                            __(
                                'Resurs Bank price information is currently unavailable right now, due to timed out ' .
                                'connection. Please try again in a moment.'
                            )
                        )
                    );
                }
            }
        }
    }

    /**
     * Carefully check and return order from Resurs Bank but only if it does exist. We do this by checking
     * if the payment method allows us doing a getPayment and if true, then we will return a new WC_Order with
     * Resurs Bank ecom metadata included.
     *
     * @param int|WC_Order $orderData
     * @return array|null
     * @throws ResursException
     * @since 0.0.1.0
     */
    public static function getResursOrderIfExists($orderData)
    {
        $return = null;
        $order = WooCommerce::getProperOrder($orderData, 'order');
        if (self::isResursMethod($order->get_payment_method())) {
            $resursOrder = self::getOrderInfo($order);
            if (!empty($resursOrder['ecom']) && isset($resursOrder['ecom']->id)) {
                $return = $resursOrder;
            }
        }

        return $return;
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
            $return = self::getDefault($key);
        }
        $getOptionReturn = get_option($optionKeyPrefix);

        if (!empty($getOptionReturn)) {
            $return = $getOptionReturn;
        }

        // What the old plugin never did to save space.
        $testBoolean = self::getTruth($return);

        /** @noinspection NullCoalescingOperatorCanBeUsedInspection */
        if ($testBoolean !== null) {
            $return = $testBoolean;
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
    public static function getPrefix($extra = null): string
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
        return self::$formFieldDefaults[$key]['default'] ?? '';
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
            case 'US':
                // Resides here as an example.
                $minimumPaymentLimit = (float)WordPress::applyFilters('getMinimumAnnuityPrice', 15, $customerCountry);
                break;
            case 'FI':
                $minimumPaymentLimit = (float)WordPress::applyFilters('getMinimumAnnuityPrice', 15, $customerCountry);
                break;
            default:
                $minimumPaymentLimit = (float)WordPress::applyFilters('getMinimumAnnuityPrice', 150, $customerCountry);
        }

        $monthlyPrice = ResursBankAPI::getResurs()->getAnnuityPriceByDuration(
            $wcDisplayPrice,
            $annuityMethod,
            $annuityDuration
        );
        if ($monthlyPrice >= $minimumPaymentLimit || self::getTestMode()) {
            $annuityPaymentMethod = (array)self::getPaymentMethodById($annuityMethod);

            // Customized string.
            $partPayString = self::getPartPayStringByTags(
                WordPress::applyFilters(
                    'partPaymentString',
                    sprintf(
                        __(
                            'Part pay from %s per month. | %s',
                            'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                        ),
                        self::getWcPriceSpan($monthlyPrice),
                        self::getReadMoreString($annuityPaymentMethod, $monthlyPrice)
                    )
                ),
                [
                    'currency' => get_woocommerce_currency_symbol(),
                    'monthlyPrice' => (float)$monthlyPrice,
                    'monthlyDuration' => (int)$annuityDuration,
                    'paymentLimit' => (int)$minimumPaymentLimit,
                    'paymentMethod' => $annuityPaymentMethod,
                    'isTest' => self::getTestMode(),
                    'readmore' => self::getReadMoreString($annuityPaymentMethod, $monthlyPrice),
                ]
            );

            // Fetch the rest from the template and display.
            echo Data::getEscapedHtml(
                self::getGenericClass()->getTemplate(
                    'product_annuity.phtml',
                    [
                        'currency' => get_woocommerce_currency_symbol(),
                        'monthlyPrice' => $monthlyPrice,
                        'monthlyDuration' => $annuityDuration,
                        'partPayString' => $partPayString,
                        'paymentMethod' => $annuityPaymentMethod,
                        'isTest' => self::getTestMode(),
                        'readmore' => self::getReadMoreString($annuityPaymentMethod, $monthlyPrice),
                    ]
                )
            );
        }
    }

    /**
     * Case fixed sanitizer, cloned from WordPress own functions, for which we sanitize keys based on
     * element id's. Resurs Bank is very much built on case-sensitive values for why we want to sanitize
     * on both lower- and uppercase.
     * @param $key
     * @return mixed|void
     * @since 0.0.1.1
     */
    public static function sanitize_key_element($key)
    {
        $sanitized_key = '';

        if (is_scalar($key)) {
            $sanitized_key = preg_replace('/[^a-z0-9_\-]/i', '', $key);
        }

        return apply_filters('sanitize_key', $sanitized_key, $key);
    }

    /**
     * Centralized escaper for internal templates.
     *
     * @param $content
     * @return string
     * @since 0.0.1.1
     */
    public static function getEscapedHtml($content)
    {
        return wp_kses(
            $content,
            self::getSafeTags()
        );
    }

    /**
     * Get safe escape tags for html. Observe that we pass some of the elements through a purger, as
     * some of the script based "on"-elements are limited to admin.
     *
     * @return array
     * @since 0.0.1.1
     */
    private static function getSafeTags()
    {
        // Many of the html tags is depending on clickable elements, but we're limiting them here
        // to only apply in the most important elements.
        $return = [
            'a' => [
                'href' => [],
                'target' => [],
            ],
            'br' => [],
            'table' => [
                'id' => [],
                'name' => [],
                'class' => [],
                'style' => [],
                'width' => [],
            ],
            'tr' => [
                'id' => [],
                'name' => [],
                'class' => [],
                'style' => [],
            ],
            'th' => [
                'id' => [],
                'name' => [],
                'class' => [],
                'style' => [],
                'scope' => [],
            ],
            'td' => [
                'id' => [],
                'name' => [],
                'class' => [],
                'style' => [],
            ],
            'label' => [
                'for' => [],
                'style' => [],
                'class' => [],
                'onclick' => [],
            ],
            'div' => [
                'style' => [],
                'id' => [],
                'name' => [],
                'class' => [],
                'label' => [],
                'onclick' => [],
            ],
            'p' => [
                'style' => [],
                'class' => [],
                'label' => [],
            ],
            'span' => [
                'id' => [],
                'name' => [],
                'label' => [],
                'class' => [],
                'style' => [],
                'onclick' => [],
            ],
            'select' => [
                'option' => [],
                'class' => [],
                'id' => [],
                'onclick' => [],
            ],
            'option' => [],
            'button' => [
                'id' => [],
                'name' => [],
                'class' => [],
                'style' => [],
                'onclick' => [],
                'type' => [],
            ],
            'iframe' => [
                'src' => [],
                'class' => [],
                'style' => [],
            ],
            'input' => [
                'id' => [],
                'name' => [],
                'type' => [],
                'size' => [],
                'onkeyup' => [],
                'value' => [],
                'class' => [],
                'readonly' => [],
            ],
            'h1' => [],
            'h2' => [],
            'h3' => [
                'style' => [],
                'class' => [],
            ],
        ];

        return self::purgeSafeAdminTags($return);
    }

    /**
     * Purge some html sanitizer elements before returning them to wp_kses.
     * @since 0.0.1.1
     */
    private static function purgeSafeAdminTags($return)
    {
        if (!is_admin()) {
            $unsetPublicClicks = [
                'select',
                'h1',
                'h2',
                'h3',
            ];

            foreach ($unsetPublicClicks as $element) {
                if (isset($return[$element]['onclick'])) {
                    unset($return[$element]['onclick']);
                }
            }
        }

        return $return;
    }

    /**
     * Allowing specific styles if admin is active.
     *
     * @since 0.0.1.1
     */
    public static function getAdminSafeCss()
    {
        if (is_admin()) {
            add_filter('safe_style_css', function ($styles) {
                $styles[] = 'display';
                $styles[] = 'border';
                return $styles;
            });
        }
    }

    /**
     * Escaping html where we only new a few elements.
     *
     * @return string
     * @since 0.0.1.1
     */
    public static function getTinyEscapedHtml($content)
    {
        return wp_kses(
            $content,
            [
                'div' => [
                    'class' => [],
                    'style' => [],
                ],
                'span' => [
                    'class' => [],
                    'style' => [],
                ],
            ]
        );
    }

    /**
     * @return false|mixed|void
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getCustomerCountry()
    {
        global $woocommerce;

        /**
         * @var WC_Customer $wcCustomer
         */
        $wcCustomer = $woocommerce->customer;

        $return = null;
        if ($wcCustomer instanceof WC_Customer && method_exists($wcCustomer, 'get_billing_country')) {
            $woocommerceCustomerCountry = $wcCustomer->get_billing_country();
            $return = !empty($woocommerceCustomerCountry) ?
                $woocommerceCustomerCountry : WordPress::applyFilters(
                    'getDefaultCountry',
                    get_option('woocommerce_default_country')
                );
        }

        return $return;
    }

    /**
     * Returns test mode boolean.
     *
     * @return bool
     * @since 0.0.1.0
     */
    public static function getTestMode(): bool
    {
        return in_array(self::getResursOption('environment'), ['test', 'staging']);
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

        $storedMethods = ResursBankAPI::getPaymentMethods();
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
     * @param $content
     * @since 0.0.1.0
     */
    private static function getPartPayStringByTags($content, $data)
    {
        $tags = self::getCompatibleTags(
            [
                'currency' => get_woocommerce_currency_symbol(),
                'monthlyPrice' => $data['monthlyPrice'],
                'monthlyDuration' => $data['monthlyDuration'],
                'readmore' => $data['readmore'],
            ],
            $data
        );
        $replaceTags = [];
        $replaceWith = [];
        foreach ($tags as $tagKey => $tagValue) {
            if ($tagKey === 'payFrom') {
                $tagValue = self::getWcPriceSpan($data['monthlyPrice'], ['currency' => ' ']);
            }
            $replaceTags[] = sprintf('/\[%s\]/i', $tagKey);
            $replaceWith[] = $tagValue;
        }

        $methodTags = [
            'id',
            'description',
            'type',
            'specificType',
        ];

        if (!empty($data['paymentMethod'])) {
            foreach ($data['paymentMethod'] as $methodKey => $methodValue) {
                if (is_string($methodValue) && in_array($methodKey, $methodTags, true)) {
                    $replaceTags[] = sprintf('/\[method%s\]/i', ucfirst($methodKey));
                    $replaceWith[] = $methodValue;
                }
            }
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
    private static function getCompatibleTags($replaceTags, $data): array
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
     * @param array $wcPriceRequest
     * @return string
     * @since 0.0.1.0
     */
    private static function getWcPriceSpan($monthlyPrice, $wcPriceRequest = []): string
    {
        return sprintf('<span id="r_annuity_price">%s</span>', wc_price($monthlyPrice, $wcPriceRequest));
    }

    /**
     * @param $annuityPaymentMethod
     * @param $monthlyPrice
     * @return string
     * @since 0.0.1.0
     * @noinspection LongLine
     * @noinspection BadExpressionStatementJS
     * Expression is location in the js parts by the way.
     */
    public static function getReadMoreString($annuityPaymentMethod, $monthlyPrice): string
    {
        return sprintf(
            '<span style="cursor:pointer !important; font-weight:bold;" onclick="getRbReadMoreClicker(\'%s\', \'%s\')">
            %s
            </span>',
            isset($annuityPaymentMethod['id']) ? sanitize_text_field($annuityPaymentMethod['id']) : 'not-set',
            (float)$monthlyPrice,
            esc_html(
                WordPress::applyFilters(
                    'partPaymentReadMoreString',
                    __('Read more.', 'tornevalls-resurs-bank-payment-gateway-for-woocommerce')
                )
            )
        );
    }

    /**
     * @return Generic
     * @since 0.0.1.0
     */
    public static function getGenericClass(): Generic
    {
        if (self::$genericClass !== Generic::class) {
            self::$genericClass = new Generic();
            self::$genericClass->setTemplatePath(self::getGatewayPath('templates'));
        }

        return self::$genericClass;
    }

    /**
     * Initialize default data from formFields.
     *
     * @since 0.0.1.0
     */
    public static function getDefaultsInit(): array
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
    private static function getDefaultsFromSections($array): array
    {
        $return = [];
        /**
         * We want the content, not the section value.
         * @noinspection PhpUnusedLocalVariableInspection
         */
        foreach ($array as $section => $content) {
            $return += $content;
        }

        return $return;
    }

    /**
     * @param string $specificMock
     * @param bool $resetMock Ability to not reset mock mode for specific mock if it has to be checked first.
     * @return bool
     * @since 0.0.1.0
     */
    public static function canMock($specificMock, $resetMock = true): bool
    {
        $return = false;
        if (self::isTest() && (bool)self::getResursOption('allow_mocking', null, false)) {
            $mockOptionName = Strings::returnSnakeCase(sprintf('mock%s', ucfirst($specificMock)));
            if (self::getResursOption(
                $mockOptionName,
                null,
                false
            )) {
                if ($resetMock) {
                    // Disable mockoption after first execution.
                    self::setResursOption($mockOptionName, false);
                }
                return true;
            }
        }

        return $return;
    }

    /**
     * @return bool
     * @since 0.0.1.0
     */
    public static function isTest(): bool
    {
        return (self::getResursOption('environment', null, false) === 'test');
    }

    /**
     * @param $key
     * @param $value
     * @return bool
     * @since 0.0.1.0
     */
    public static function setResursOption($key, $value): bool
    {
        return update_option(sprintf('%s_%s', self::getPrefix('admin'), $key), $value);
    }

    /**
     * @return bool
     * @since 0.0.1.0
     */
    public static function hasDefaults(): bool
    {
        return is_array(self::$formFieldDefaults) && count(self::$formFieldDefaults);
    }

    /**
     * @return string
     * @version 0.0.1.0
     */
    public static function getGatewayBackend(): string
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
    public static function getPluginScripts($isAdmin = null): array
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
    public static function getPluginStyles($isAdmin = null): array
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
    public static function getJsDependencies($scriptName, $isAdmin): array
    {
        if ($isAdmin) {
            $return = self::$jsDependenciesAdmin[$scriptName] ?? [];
        } else {
            $return = self::$jsDependencies[$scriptName] ?? [];
        }

        return $return;
    }

    /**
     * Get current payment method from session.
     *
     * @return string
     * @since 0.0.1.0
     */
    public static function getPaymentMethodBySession(): string
    {
        return (string)WooCommerce::getSessionValue('paymentMethod');
    }

    /**
     * Get the name of current checkout in use.
     * @return string
     * @since 0.0.1.0
     */
    public static function getCheckoutType(): string
    {
        $currentCheckoutType = self::getResursOption('checkout_type');
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
    public static function getValidatedVersion(): bool
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
    public static function getCurrentVersion(): string
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
    private static function getPluginDataContent($key): string
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
    public static function getVersionByComposer(): string
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
    public static function getPluginTitle($getBaseName = null): string
    {
        return !$getBaseName ? self::getPluginDataContent('Plugin Name') : WooCommerce::getBaseName();
    }

    /**
     * @param bool $getBasic
     * @return array
     * @since 0.0.1.0
     */
    public static function getFormFields($getBasic = null): array
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
            __(
                'Plugin version',
                'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
            ) => esc_html(self::getCurrentVersion()),
            __('WooCommerce', 'tornevalls-resurs-bank-payment-gateway-for-woocommerce') => sprintf(
                __(
                    '%s, at least %s are required.',
                    'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                ),
                esc_html(WooCommerce::getWooCommerceVersion()),
                esc_html(WooCommerce::getRequiredVersion())
            ),
            __(
                'Composer version',
                'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
            ) => esc_html(self::getVersionByComposer()),
            __('PHP Version', 'tornevalls-resurs-bank-payment-gateway-for-woocommerce') => PHP_VERSION,
            __(
                'Webservice Library',
                'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
            ) => defined('ECOMPHP_VERSION') ? 'ecomphp-' . ECOMPHP_VERSION : '',
            __(
                'Communication Library',
                'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
            ) => esc_html('netcurl-' . $netWrapper->getVersion()),
            __(
                'Communication Drivers',
                'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
            ) => Data::getEscapedHtml(implode('<br>', self::getWrapperList($netWrapper))),
        ];

        $content .= Data::getEscapedHtml(
            self::getGenericClass()->getTemplate(
                'plugin_information',
                [
                    'required_drivers' => self::getSpecialString('required_drivers'),
                    'support_string' => self::getSpecialString('support_string'),
                    'render' => Data::getSanitizedArray(WordPress::applyFilters('renderInformationData', $renderData)),
                ]
            )
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
    private static function getWrapperList($netWrapper): array
    {
        $wrapperList = [];
        foreach ($netWrapper->getWrappers() as $wrapperClass => $wrapperInstance) {
            $wrapperList[] = esc_html(preg_replace('/(.*)\\\\(.*?)$/', '$2', $wrapperClass));
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
                'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
            ),
            'support_string' => __(
                'If you ever need support with this plugin, you should primarily check this ' .
                'page before sending support requests. When you send the requests, make sure you do ' .
                'include the information below in your message. Doing this, it will be easier ' .
                'in the end to help you out.',
                'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
            ),
        ];

        return $array[$key] ?? '';
    }

    /**
     * @return bool
     * @since 0.0.1.0
     */
    public static function isProductionAvailable(): bool
    {
        return (
            !empty(self::getResursOption('login_production')) &&
            !empty(self::getResursOption('password_production'))
        );
    }

    /**
     * @param $data
     * @return string
     * @since 0.0.1.0
     */
    public static function setEncryptData($data): string
    {
        $dataEncryptionState = null;
        try {
            $crypt = self::getCrypt();
            $return = $crypt->aesEncrypt($data);
        } catch (Exception $e) {
            $dataEncryptionState = $e;
            $return = (new Strings())->base64urlEncode($data);
        }

        if ($dataEncryptionState instanceof Exception) {
            self::setLogNotice(
                sprintf(
                    __('%s failed encryption (%d): %s. Failover to base64.',
                        'tornevalls-resurs-bank-payment-gateway-for-woocommerce'),
                    __FUNCTION__,
                    $dataEncryptionState->getCode(),
                    $dataEncryptionState->getMessage()
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
    public static function getCrypt(): Aes
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

        $from = $_SERVER['REMOTE_ADDR'] ?? 'Console';
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
        }
    }

    /**
     * @param $data
     * @param bool $base64
     * @return mixed
     * @since 0.0.1.0
     * @noinspection BadExceptionsProcessingInspection
     */
    public static function getDecryptData($data, $base64 = false)
    {
        try {
            if ($base64) {
                $return = (new Strings())->base64urlDecode($data);
            } else {
                $crypt = self::getCrypt();
                $return = $crypt->aesDecrypt($data);
            }
        } catch (Exception $e) {
            self::setLogException($e);
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
        return self::getResursOption('enabled', null, false);
    }

    /**
     * @param $fromFunction
     * @param $message
     * @return bool
     * @since 0.0.1.0
     */
    public static function setDeveloperLog($fromFunction, $message): bool
    {
        return self::canLog(
            self::CAN_LOG_ORDER_DEVELOPER,
            sprintf(
                __(
                    'DevLog Method "%s" message: %s.',
                    'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
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
    public static function canLog($eventType, $logData): bool
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
    public static function getOrderInfo($order, $orderIsResursReference = null): array
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
            $foundOrderId = self::getOrderByEcomRef($order);
            if ($foundOrderId) {
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
            $getPostId = self::getRefVarFromMeta($key, $orderReference);
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
    private static function getRefVarFromMeta($key, $reference): int
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

        return $return ?? 0;
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
    public static function getPrefetchedPayment($key): array
    {
        return self::$payments[$key] ?? [];
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
     * @param string|array $searchFor
     * @return string
     */
    public static function getResursReference($orderDataArray, $searchFor = null): string
    {
        $return = '';

        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $searchUsing = !empty($searchFor) && is_array($searchFor) ? $searchFor : self::$searchArray;
        if (is_array($orderDataArray) && isset($orderDataArray['meta'])) {
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
                    $return['ecom'] = ResursBankAPI::getPayment($return['resurs'], null, $return);
                    $return['ecom_had_reference_problems'] = false;
                } catch (Exception $e) {
                    self::setTimeoutStatus(ResursBankAPI::getResurs(), $e);
                    if (!empty($return['resurs_secondary']) && $return['resurs_secondary'] !== $return['resurs']) {
                        $return['ecom'] = ResursBankAPI::getPayment($return['resurs_secondary'], null, $return);
                    }
                    $return['ecom_had_reference_problems'] = true;
                    $return['ecomException']['code'] = $e->getCode();
                    $return['ecomException']['message'] = $e->getMessage();
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
            'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
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
                    '%s internal generic exception %s: %s --- File %s, line %s.',
                    'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                ),
                self::getPrefix(),
                $exception->getCode(),
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine()
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
            'resursOrder' => $orderData['resurs'] ?? '',
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
    public static function hasOldGateway(): bool
    {
        return defined('RB_WOO_VERSION') ? true : false;
    }

    /**
     * Makes sure nothing interfering with orders that has not been created by us. If this returns false,
     * it means we should not be there and touch things.
     *
     * @param $thisMethod
     * @return bool
     * @since 0.0.1.0
     */
    public static function canHandleOrder($thisMethod): bool
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
                if (self::isDeprecatedPluginOrder($thisMethod)) {
                    $isResursDeprecated = true;
                    break;
                }
                $return = true;
                break;
            }
        }

        if ($isResursDeprecated && self::getResursOption('deprecated_interference')) {
            $return = true;
        }

        return $return;
    }

    /**
     * @param $thisMethod
     * @return bool
     * @since 0.0.1.0
     */
    public static function isDeprecatedPluginOrder($thisMethod): bool
    {
        return strncmp($thisMethod, 'resurs_bank_', 12) === 0;
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
                        'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
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
    public static function getGeneralSettings($settings = null): array
    {
        foreach ((array)$settings as $setting) {
            if (isset($setting['id']) && $setting['id'] === 'woocommerce_price_num_decimals') {
                $currentPriceDecimals = wc_get_price_decimals();
                if ($currentPriceDecimals < 2 && self::getResursOption('prevent_rounding_panic')) {
                    $settings[] = [
                        'title' => __('Number of decimals headsup message',
                            'tornevalls-resurs-bank-payment-gateway-for-woocommerce'),
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
    public static function isGetAddressSupported(): bool
    {
        $return = in_array(
            self::getCustomerCountry(),
            WordPress::applyFilters(
                'getCompatibleGetAddressCountries',
                ['NO', 'SE']
            ),
            true
        );

        return $return;
    }

    /**
     * Complex settings for getAddress forms.
     *
     * @return bool|mixed
     * @since 0.0.1.0
     */
    public static function canUseGetAddressForm()
    {
        $getAddressDisabled = (bool)WordPress::applyFilters('getAddressDisabled', false);
        $getAddressFormDefault = (bool)WordPress::applyFiltersDeprecated(
            'resurs_getaddress_enabled',
            self::getResursOption('get_address_form')
        );
        if ($getAddressDisabled) {
            // Filter overrides the config settings.
            $getAddressFormDefault = $getAddressDisabled;
        }

        return $getAddressFormDefault;
    }

    /**
     * @return string
     * @since 0.0.1.0
     */
    public static function getErrorNotices(): string
    {
        $wcNotices = wc_get_notices();
        $internalErrorMessage = '';
        if (isset($wcNotices['error']) && count($wcNotices['error'])) {
            $wcErrorCollection = [];
            foreach ($wcNotices['error'] as $arr) {
                $wcErrorCollection[] = $arr['notice'];
            }
            $internalErrorMessage = implode("<br>\n", $wcErrorCollection);
            self::canLog(
                self::LOG_ERROR,
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
        $customerTypeByGetAddress = Data::getRequest('resursSsnCustomerType', true);

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
        $customerTypeByCompanyName = self::getRequest('billing_company', true);

        return empty($customerTypeByCompanyName) ? $return : 'LEGAL';
    }

    /**
     * @param $key
     * @param bool|array $post_data
     * @return mixed
     * @since 0.0.1.1
     */
    public static function getRequest($key, $post_data = null)
    {
        if (is_array($post_data)) {
            $requestArray = $post_data;
        } else {
            $requestArray = $_REQUEST;
        }
        $request = self::getSanitizedRequest($requestArray);
        $return = $request[$key] ?? null;

        if ($return === null && (bool)$post_data && isset($_REQUEST['post_data'])) {
            parse_str($_REQUEST['post_data'], $newPostData);
            if (is_array($newPostData)) {
                $newPostData = self::getSanitizedArray($newPostData);
                $return = $newPostData[$key];
            }
        }

        return $return;
    }

    /**
     * $_REQUEST-sanitizer.
     * @return array
     * @since 0.0.1.1
     */
    public static function getSanitizedRequest($requestArray)
    {
        $returnRequest = [];
        if (is_array($requestArray)) {
            $returnRequest = self::getSanitizedArray($requestArray);
        }
        return $returnRequest;
    }

    /**
     * Recursive string sanitizer.
     *
     * @param $array
     * @since 0.0.1.1
     */
    public static function getSanitizedArray($array)
    {
        $arrays = new Arrays();
        $returnArray = [];
        if ($arrays->isAssoc($array)) {
            foreach ($array as $arrayKey => $arrayValue) {
                if (is_array($arrayValue)) {
                    $returnArray[$arrayKey] = self::getSanitizedArray($arrayValue);
                } elseif (is_string($arrayValue)) {
                    $returnArray[$arrayKey] = sanitize_text_field($arrayValue);
                }
            }
        } elseif ($array) {
            foreach ($array as $item) {
                if (is_array($item)) {
                    $returnArray[] = self::getSanitizedArray($arrayValue);
                } elseif (is_string($item)) {
                    $returnArray[] = sanitize_text_field($item);
                }
            }
        }

        return $returnArray;
    }

    /**
     * @return bool
     * @since 0.0.1.0
     */
    public static function clearCredentialNotice(): bool
    {
        return self::delResursOption('front_callbacks_credential_error');
    }

    /**
     * Remove self settings from option.
     * @param $key
     * @return bool
     */
    public static function delResursOption($key): bool
    {
        return delete_option(sprintf('%s_%s', self::getPrefix('admin'), $key));
    }

    /**
     * @return bool
     * @since 0.0.1.0
     */
    public static function getCredentialNotice(): bool
    {
        return self::setResursOption(
            'front_callbacks_credential_error',
            json_encode(
                [
                    'code' => 401,
                    'message' => __(
                        'Received an error message from Resurs Bank that indicates that you credentials are incorrect.',
                        'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                    ),
                ]
            )
        );
    }

    /**
     * Check whether bleeding edge technology is enabled.
     * @return bool
     * @since 0.0.1.0
     */
    public static function isBleedingEdge(): bool
    {
        return (bool)self::getResursOption('bleeding_edge');
    }

    /**
     * Check if account for bleeding edge API has been set up.
     * @return bool
     * @since 0.0.1.0
     */
    public static function isBleedingEdgeApiReady(): bool
    {
        return (self::isBleedingEdge() &&
            !empty(self::getResursOption('jwt_store_id')) &&
            !empty(self::getResursOption('jwt_client_id')) &&
            !empty(self::getResursOption('jwt_client_password')));
    }

    /**
     * @param $paymentMethod
     * @return bool
     * @since 0.0.1.0
     */
    public static function isResursMethod($paymentMethod): bool
    {
        $return = (bool)preg_match(sprintf('/^%s_/', self::getPrefix()), $paymentMethod);

        if (!$return && Data::canHandleOrder($paymentMethod)) {
            $return = true;
        }

        return $return;
    }
}
