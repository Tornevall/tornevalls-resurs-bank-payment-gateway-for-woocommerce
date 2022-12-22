<?php

/** @noinspection EfferentObjectCouplingInspection */
/** @noinspection SpellCheckingInspection */
/** @noinspection ParameterDefaultValueIsNotNullInspection */

namespace ResursBank\Module;

use Exception;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Lib\Log\LogLevel;
use ResursBank\Service\WooCommerce;
use ResursBank\Service\WordPress;
use Resursbank\Woocommerce\Database\Options\ClientId;
use Resursbank\Woocommerce\Database\Options\ClientSecret;
use Resursbank\Woocommerce\Database\Options\Enabled;
use Resursbank\Woocommerce\Database\Options\Environment;
use RuntimeException;
use stdClass;
use WC_Customer;
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
     * @var int
     * @since 0.0.1.4
     */
    public const UNSET_CREDENTIALS_EXCEPTION = 4444;

    /**
     * @var string
     * @since 0.0.1.0
     */
    public const CAN_LOG_JUNK = 'junk';

    /**
     * Can log all backend calls.
     * var string
     * @since 0.0.1.0
     */
    public const CAN_LOG_BACKEND = 'backend';

    /**
     * @var string
     * @since 0.0.1.0
     */
    public const CAN_LOG_ORDER_EVENTS = 'order_events';

    /**
     * @var string
     * @since 0.0.1.0
     */
    public const CAN_LOG_ORDER_DEVELOPER = 'order_developer';

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
     * @var array $jsLoadersAdmin List of loadable scripts for admin.
     * @since 0.0.1.0
     */
    private static $jsLoadersAdmin = [
        'resursbank_all' => 'resursbank_global.js',
        'resursbank_admin' => 'resursbank_admin.js',
    ];

    /**
     * @var array $jsDependencies List of dependencies for the scripts in this plugin.
     * @since 0.0.1.0
     */
    private static $jsDependencies = [
        'resursbank' => ['jquery']
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
    private static $fileImageExtensions = ['jpg', 'gif', 'png', 'svg'];

    /**
     * @var array $formFieldDefaults
     * @since 0.0.1.0
     */
    private static $formFieldDefaults = [];

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
            self::getImagePath(),
            $imageName
        );
        $realImageFile = sprintf(
            '%s/%s',
            self::getImagePath(false),
            $imageName
        );

        $hasExtension = (bool)preg_match('/\./', $imageName);

        // Match allowed file extensions and return if it exists within the file name.
        if ($hasExtension && (bool)preg_match(
                sprintf('/^(.*?)(.%s)$/', implode('|.', self::$fileImageExtensions)),
                $imageFile
            )) {
            $imageFile = preg_replace(
                sprintf('/^(.*)(.%s)$/', implode('|.', self::$fileImageExtensions)),
                '$1',
                $imageFile
            );
            $realImageFile = preg_replace(
                sprintf('/^(.*)(.%s)$/', implode('|.', self::$fileImageExtensions)),
                '$1',
                $realImageFile
            );
        }

        $internalUrl = false;
        foreach (self::$fileImageExtensions as $extension) {
            if (self::getImageFileNameWithExtension($imageFile, $extension)) {
                if (false === strpos($imageName, '.')) {
                    $imageName .= '.' . $extension;
                }
                $imageFileName = $imageFile . '.' . $extension;
                break;
            }
            if (self::getImageFileNameWithExtension($realImageFile, $extension)) {
                // Override when it exists internally.
                $internalUrl = true;
                if (false === strpos($imageName, '.')) {
                    $imageName .= '.' . $extension;
                }
                $imageFileName = $realImageFile . '.' . $extension;
                break;
            }
        }

        $imageUrl = $internalUrl ? self::getImageUrl($imageName) : self::getImageUrl($imageName, true);

        return $imageFileName !== null ? $imageUrl : null;
    }

    /**
     * @param bool $filtered
     * @return string
     * @since 0.0.1.8
     */
    public static function getImagePath(bool $filtered = true): string
    {
        $subPathTest = preg_replace('/\//', '', 'images');
        $gatewayPath = preg_replace(
            '/\/+$/',
            '',
            RESURSBANK_GATEWAY_PATH
        );
        if ($filtered) {
            $gatewayPath = preg_replace(
                '/\/+$/',
                '',
                WordPress::applyFilters('getImageGatewayPath', RESURSBANK_GATEWAY_PATH)
            );
        }

        if (!empty($subPathTest) && file_exists($gatewayPath . '/' . $subPathTest)) {
            $gatewayPath .= '/' . $subPathTest;
        }

        return $gatewayPath;
    }

    /**
     * @param $imageFile
     * @param $extension
     * @return bool
     * @since 0.0.1.8
     */
    private static function getImageFileNameWithExtension($imageFile, $extension): bool
    {
        $imageFileName = sprintf('%s.%s', $imageFile, $extension);

        return file_exists($imageFileName);
    }

    /**
     * @param null $imageFileName
     * @param bool $filtered
     * @return string
     * @version 0.0.1.0
     */
    private static function getImageUrl($imageFileName = null, bool $filtered = false): string
    {
        if ($filtered) {
            $return = sprintf(
                '%s/images',
                WordPress::applyFilters('getImageGatewayUrl', self::getGatewayUrl())
            );
        } else {
            $return = sprintf(
                '%s/images',
                self::getGatewayUrl()
            );
        }

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
     * Get file path for major initializer (init.php).
     *
     * @param string $subDirectory
     * @return string
     * @version 0.0.1.0
     */
    public static function getGatewayPath(string $subDirectory = ''): string
    {
        $subPathTest = preg_replace('/\//', '', $subDirectory);
        $gatewayPath = preg_replace('/\/+$/', '', RESURSBANK_GATEWAY_PATH);

        if (!empty($subPathTest) && file_exists($gatewayPath . '/' . $subPathTest)) {
            $gatewayPath .= '/' . $subPathTest;
        }

        return $gatewayPath;
    }

    /**
     * @return bool
     * @throws ConfigException
     * @since 0.0.1.0
     */
    public static function isTest(): bool
    {
        return !Config::isProduction();
    }

    /**
     * @param mixed $key
     * @param mixed $namespace
     * @param bool $getDefaults
     * @return mixed
     * @since 0.0.1.0
     */
    public static function getResursOption(
        $key,
        $namespace = '',
        bool $getDefaults = true
    ) {
        $return = null;

        if (!is_null($namespace) && preg_match('/woocom(.*?)resurs/', $namespace)) {
            return self::getResursOptionDeprecated($key, $namespace);
        }
        $optionKeyPrefix = sprintf('%s_%s', self::getPrefix('admin', true), $key);
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
            // If testBoolean returned anything but null, we know the value returned from option
            // fetcher is a boolean and therefore return the value as such. With this in mind
            // values expected as booleans can not be anything but a boolean.
            $return = $testBoolean;
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
        if (in_array($value, ['true', 'yes'], true)) {
            $return = true;
        } elseif (in_array($value, ['false', 'no'], true)) {
            $return = false;
        } else {
            $return = null;
        }

        return $return;
    }

    /**
     * @param null $extra
     * @param null $ignoreCodeBase
     * @return string
     * @since 0.0.1.0
     */
    public static function getPrefix($extra = null, $ignoreCodeBase = null): string
    {
        // If no extra data are added in the prefix handler, use the regular prefix.
        if (empty($extra)) {
            // If we're in the original codebase slug, we should not allow any filters to hijack us.
            if (!$ignoreCodeBase && self::isOriginalCodeBase()) {
                $return = RESURSBANK_PREFIX;
            } else {
                // If we're in a forked repo, go for it.
                $return = WordPress::applyFilters('getPluginPrefix', RESURSBANK_PREFIX);
            }
        } elseif ($ignoreCodeBase || self::isOriginalCodeBase()) {
            $return = sprintf(
                '%s_%s',
                RESURSBANK_PREFIX,
                $extra
            );
        } else {
            // If we're in a forked repo, go for it.
            $return = sprintf(
                '%s_%s',
                WordPress::applyFilters('getPluginPrefix', RESURSBANK_PREFIX),
                $extra
            );
        }

        return $return;
    }

    /**
     * @return bool
     * @since 0.0.1.6
     */
    public static function isOriginalCodeBase(): bool
    {
        return WooCommerce::getBaseName() === 'resurs-bank-payments-for-woocommerce';
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
     * Check if payment method for specific credentials are enabled or disabled.
     * @return bool
     * @throws Exception
     * @since 0.0.1.6
     */
    public static function isPaymentMethodEnabled($paymentMethod): bool
    {
        return self::getPaymentMethodSetting('enabled', $paymentMethod) ?? true;
    }

    /**
     * @param string $key
     * @param $paymentMethod
     * @return mixed|null
     * @throws Exception
     * @since 0.0.1.6
     */
    public static function getPaymentMethodSetting(string $key, $paymentMethod)
    {
        if (!empty($paymentMethod)) {
            $settingBlock = (array)self::getPaymentMethodSettings($paymentMethod);
        }

        return $settingBlock[$key] ?? null;
    }

    /**
     * @param string $paymentMethod
     * @return mixed
     * @throws Exception
     * @since 0.0.1.6
     */
    private static function getPaymentMethodSettings(string $paymentMethod)
    {
        return Data::getResursOption(
            sprintf(
                '%s_settings',
                self::getPaymentMethodNameSpace($paymentMethod)
            )
        ) ?: [];
    }

    /**
     * Resolve namespace by current environment setup (using environment_username_paymentmethod).
     *
     * @param $paymentMethod
     * @return string
     * @throws Exception
     * @since 0.0.1.6
     */
    private static function getPaymentMethodNameSpace($paymentMethod): string
    {
        WooCommerce::applyMock('getPaymentMethodNamespaceException');

        return sprintf(
            '%s_%s',
            (new ResursBankAPI())->getCredentialString(),
            $paymentMethod
        );
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
    public static function getAnnuityFactors($productPrice = null, $display = true)
    {
        global $product;

        $return = null;

        $currentFactor = self::getResursOption('currentAnnuityFactor');
        if ((is_object($product) || $productPrice > 0) && !empty($currentFactor)) {
            if (is_object($product)) {
                $productPrice = wc_get_price_to_display($product);
            }

            try {
                $return = self::getAnnuityHtml(
                    (float)$productPrice,
                    self::getResursOption('currentAnnuityFactor'),
                    (int)self::getResursOption('currentAnnuityDuration')
                );
            } catch (Exception $annuityException) {
            }
        }

        if ($display) {
            echo $return;
        } else {
            return $return;
        }
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

        $monthlyPrice = ResursBankAPI::getResurs()->getAnnuityPriceByDuration(
            $wcDisplayPrice,
            $annuityMethod,
            $annuityDuration
        );
        $defaultThreshold = self::getDefaultPartPaymentThreshold($customerCountry);

        if ($monthlyPrice >= $defaultThreshold || self::getTestMode()) {
            $annuityPaymentMethod = (array)self::getPaymentMethodById($annuityMethod);

            // Customized string.
            $partPayString = self::getPartPayStringByTags(
                WordPress::applyFilters(
                    'partPaymentString',
                    sprintf(
                        __(
                            'Part pay from %s per month. | %s',
                            'resurs-bank-payments-for-woocommerce'
                        ),
                        self::getWcPriceSpan($monthlyPrice),
                        self::getReadMoreString($annuityPaymentMethod, $monthlyPrice)
                    )
                ),
                [
                    'currency' => get_woocommerce_currency_symbol(),
                    'monthlyPrice' => (float)$monthlyPrice,
                    'monthlyDuration' => (int)$annuityDuration,
                    'paymentLimit' => $defaultThreshold,
                    'paymentMethod' => $annuityPaymentMethod,
                    'isTest' => self::getTestMode(),
                    'readmore' => self::getReadMoreString($annuityPaymentMethod, $monthlyPrice),
                ]
            );

            // Fetch the rest from the template and display.
            return Data::getEscapedHtml(
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
     * @return string
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getCustomerCountry(): string
    {
        global $woocommerce;

        /**
         * @var WC_Customer $wcCustomer
         */
        $wcCustomer = $woocommerce->customer;

        $return = WordPress::applyFilters(
            filterName: 'getDefaultCountry',
            value: get_option('woocommerce_default_country')
        );

        if ($wcCustomer instanceof WC_Customer) {
            $woocommerceCustomerCountry = $wcCustomer->get_billing_country();
            if (!empty($woocommerceCustomerCountry)) {
                $return = $woocommerceCustomerCountry;
            }
        }

        return $return;
    }

    /**
     * @param $customerCountry
     * @return float
     * @since 0.0.1.6
     */
    private static function getDefaultPartPaymentThreshold($customerCountry): float
    {
        $threshold = (float)Data::getResursOption('part_payment_threshold');

        if ($threshold === 150.00 && $customerCountry === 'FI') {
            $threshold = 15.00;
        } elseif ((int)$threshold === 0) {
            $threshold = 150.00;
        }

        return (float)WordPress::applyFilters('getMinimumAnnuityPrice', $threshold, $customerCountry);
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
                    __('Read more.', 'resurs-bank-payments-for-woocommerce')
                )
            )
        );
    }

    /**
     * Centralized escaper for internal templates.
     *
     * @param $content
     * @return string
     * @since 0.0.1.1
     */
    public static function getEscapedHtml($content): string
    {
        return wp_kses(
            $content,
            self::getSafeTags()
        );
    }

    /**
     * Centralized list of safe escape tags for html. Returned to WordPress method wp_kses for the proper escaping.
     *
     * @return array
     * @since 0.0.1.1
     */
    private static function getSafeTags(): array
    {
        // Many of the html tags is depending on clickable elements both in storefront and wp-admin,
        // but we're mostly limiting them here to only apply in the most important elements.
        $return = [
            'br' => [],
            'style' => [],
            'h1' => [],
            'h2' => [],
            'h3' => [
                'style' => [],
                'class' => [],
            ],
            'a' => [
                'href' => [],
                'target' => [],
                'onclick' => true
            ],
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
                'scope' => [],
                'valign' => [],
            ],
            'th' => [
                'id' => [],
                'name' => [],
                'class' => [],
                'style' => [],
                'scope' => [],
                'valign' => [],
            ],
            'td' => [
                'id' => [],
                'name' => [],
                'class' => [],
                'style' => [],
                'scope' => [],
                'valign' => [],
            ],
            'label' => [
                'for' => [],
                'style' => [],
                'class' => [],
                'onclick' => [],
            ],
            'div' => [
                'style' => true,
                'id' => [],
                'name' => [],
                'class' => [],
                'label' => [],
                'onclick' => [],
                'title' => [],
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
            'option' => [
                'value' => [],
                'selected' => [],
            ],
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
                'id' => []
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
                'onblur' => [],
                'onchange' => [],
                'checked' => [],
            ],
            'svg' => [
                'width' => true,
                'height' => true,
                'viewbox' => true,
                'version' => true,
                'id' => true,
                'xmlns' => true
            ],
            'defs' => [
                'id' => true
            ],
            'g' => [
                'id' => true,
                'transform' => true
            ],
            'path' => [
                'style' => true,
                'd' => true,
                'id' => true,
                'fill' => true
            ],
            'script' => [],
            '=' => [],
            '=>' => []
        ];

        // Run the purger, to limit clickable elements outside wp-admin.
        return self::purgeSafeAdminTags($return);
    }

    /**
     * Purge some html sanitizer elements before returning them to wp_kses, to make storefront more protective
     * against clicks than in wp-admin.
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
     * Carefully check and return order from Resurs Bank but only if it does exist. We do this by checking
     * if the payment method allows us doing a getPayment and if true, then we will return a new WC_Order with
     * Resurs Bank ecom metadata included.
     *
     * @param int|WC_Order $orderData
     * @return array|null
     * @throws ResursException
     * @since 0.0.1.0
     */
    public static function getResursOrderIfExists($orderData): ?array
    {
        $order = WooCommerce::getProperOrder($orderData, 'order');
        if (self::isResursMethod($order->get_payment_method())) {
            $resursOrder = self::getOrderInfo($order);
            if (!empty($resursOrder['ecom']) && isset($resursOrder['ecom']->id)) {
                $return = $resursOrder;
            }
        }

        return (array)(isset($return) ? $return : []);
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
            Data::getPrefix() . '_'
        ];
        $canHandleOrderMethodPrefix = (array)WordPress::applyFilters('canHandleOrderPrefix', $allowMethod);
        $allowMethod += $canHandleOrderMethodPrefix;

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
     * Advanced order fetching. Make sure you use Data::canHandleOrder($paymentMethod) before running this.
     * It is not our purpose to interfere with all orders.
     *
     * @param mixed $order
     * @param null $orderIsResursReference
     * @return mixed
     * @throws ResursException
     * @since 0.0.1.0
     */
    public static function getOrderInfo($order, $orderIsResursReference = null)
    {
        $return = [];
        $orderId = null;

        // get_id does not exist in the Order class when Override is used.
        if (is_object($order) && method_exists($order, 'get_id')) {
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

        // Looking for meta root keys.
        $metaKeys = [
            self::getPrefix(),
            'trbwc',
        ];

        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $searchUsing = !empty($searchFor) && is_array($searchFor) ? $searchFor : self::$searchArray;
        $breakSearch = false;
        if (is_array($orderDataArray) && isset($orderDataArray['meta'])) {
            foreach ($searchUsing as $searchKey) {
                foreach ($metaKeys as $prefixedMeta) {
                    $protectedMetaKey = sprintf('%s_%s', $prefixedMeta, $searchKey);
                    if (isset($orderDataArray['meta'][$searchKey])) {
                        $return = array_pop($orderDataArray['meta'][$searchKey]);
                        $breakSearch = true;
                        break;
                    }
                    if (isset($orderDataArray['meta'][$protectedMetaKey])) {
                        $return = array_pop($orderDataArray['meta'][$protectedMetaKey]);
                        $breakSearch = true;
                        break;
                    }
                }
                if ($breakSearch) {
                    break;
                }
            }
        }

        return $return;
    }

    /**
     * Fetch order info from EComPHP.
     *
     * @param $prefetchObject
     * @return mixed
     * @throws ResursException
     * @since 0.0.1.0
     */
    public static function getPreparedDataByEcom($prefetchObject)
    {
        $prefetchObject = self::getOrderInfoExceptionData($prefetchObject);
        $prefetchObject = self::getPreparedDataByExternal($prefetchObject);

        try {
            if (!$prefetchObject['ecomException']['code']) {
                $prefetchObject['apiType'] = self::getMethodApiTypeByPreFetch($prefetchObject);
                try {
                    $prefetchObject['ecom'] = ResursBankAPI::getPayment(
                        $prefetchObject['resurs'],
                        null,
                        $prefetchObject
                    );
                    $prefetchObject['ecom_had_reference_problems'] = false;
                } catch (Exception $e) {
                    if (!empty($prefetchObject['resurs_secondary']) && $prefetchObject['resurs_secondary'] !== $prefetchObject['resurs']) {
                        $prefetchObject['ecom'] = ResursBankAPI::getPayment($prefetchObject['resurs_secondary']);
                    }
                    $prefetchObject['ecom_had_reference_problems'] = true;
                    $prefetchObject['ecomException']['code'] = $e->getCode();
                    $prefetchObject['ecomException']['message'] = $e->getMessage();
                }
                $prefetchObject = WooCommerce::getFormattedPaymentData($prefetchObject);
                $prefetchObject = WooCommerce::getPaymentInfoDetails($prefetchObject);
            }
        } catch (Exception $e) {
            self::writeLogEvent(
                self::CAN_LOG_ORDER_EVENTS,
                sprintf('%s exception (%s), %s.', __FUNCTION__, $e->getCode(), $e->getMessage())
            );
            self::writeLogException($e, __FUNCTION__);
            $prefetchObject['ecomException'] = [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ];
        }
        return (array)$prefetchObject;
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
            'resurs-bank-payments-for-woocommerce'
        );
        $return['ecomException'] = [
            'message' => null,
            'code' => 0,
        ];

        return $return;
    }

    /**
     * @param $prefetchObject
     * @return array
     */
    private static function getPreparedDataByExternal($prefetchObject): array
    {
        try {
            // Collect external data before internal.
            $prefetchObject['externalPaymentInformation'] = WordPress::applyFilters(
                'getResursPayment',
                [],
                $prefetchObject['resurs'],
                $prefetchObject['resurs'] === $prefetchObject['resurs_secondary'] ? null : $prefetchObject['resurs_secondary']
            );
        } catch (Exception $externalGetpaymentException) {
            self::writeLogException(
                $externalGetpaymentException,
                sprintf('%s_filtered_get_payment', __FUNCTION__)
            );
        }

        return $prefetchObject;
    }

    /**
     * @param string $fromFunction
     * @param Exception $exception
     * @since 0.0.1.0
     */
    public static function writeLogException(Exception $exception, string $fromFunction = ''): void
    {
        if (!isset($_SESSION[self::getPrefix()])) {
            $_SESSION[self::getPrefix()]['exception'] = [];
        }
        $_SESSION[self::getPrefix()]['exception'][] = $exception;

        if (!empty($fromFunction)) {
            $logMessage = __(
                '%s internal generic exception %s from function %s: %s --- File %s, line %s.',
                'resurs-bank-payments-for-woocommerce'
            );
            self::writeLogError(
                sprintf(
                    $logMessage,
                    self::getPrefix(),
                    $exception->getCode(),
                    $fromFunction,
                    $exception->getMessage(),
                    $exception->getFile(),
                    $exception->getLine()
                )
            );
        } else {
            $logMessage = __(
                '%s internal generic exception %s: %s --- File %s, line %s.',
                'resurs-bank-payments-for-woocommerce'
            );
            self::writeLogError(
                sprintf(
                    $logMessage,
                    self::getPrefix(),
                    $exception->getCode(),
                    $exception->getMessage(),
                    $exception->getFile(),
                    $exception->getLine()
                )
            );
        }
    }

    /**
     * @param string $logMessage
     * @since 0.0.1.0
     */
    public static function writeLogError(string $logMessage): void
    {
        self::writeLogByLogLevel(
            LogLevel::ERROR,
            $logMessage
        );
    }

    /**
     * @param LogLevel $logLevel
     * @param string $message
     * @throws ConfigException
     * @since 0.0.1.0
     */
    public static function writeLogByLogLevel(LogLevel $logLevel, string $message): void
    {
        if (empty(WooCommerce::getPluginLogDir())) {
            // We no longer use WC_Log but a more safe way to write logs that should not be exposed
            // to the public.
            return;
        }

        // Checking whether the instance-logger exists or not is vital for instances that has unfinished
        // configurations. For example, when the module is installed for the first time, there won't be an ecom2
        // instance until it has been configured. As the module can log information before this happens,
        // it is also important that we only allow logging, if the instance is ready and available.
        switch ($logLevel) {
            case LogLevel::INFO:
                Config::getLogger()->info($message);
                break;
            case LogLevel::DEBUG:
                Config::getLogger()->debug($message);
                break;
            case LogLevel::ERROR:
                Config::getLogger()->error($message);
                break;
            case LogLevel::WARNING:
                Config::getLogger()->warning($message);
                break;
            default:
                Config::getLogger()->info($message);
        }
    }

    /**
     * @param $prefetchObject
     * @return string
     * @throws ResursException
     */
    private static function getMethodApiTypeByPreFetch($prefetchObject): string
    {
        $return = '';
        if (isset($prefetchObject['meta']) && is_array($prefetchObject['meta'])) {
            $paymentMethodInformation = json_decode(self::getOrderMeta('paymentMethodInformation', $prefetchObject));
            if (is_object($paymentMethodInformation) && isset($paymentMethodInformation->apiType)) {
                $return = $paymentMethodInformation->apiType;
            }
        }
        return $return;
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
        if (is_array($order) && isset($order['order'])) {
            // Get from a prefetched request.
            $orderData = $order;
        } else {
            $orderData = self::getOrderInfo($order);
        }

        if (isset($key, $orderData['meta'][$key])) {
            //$return = $orderData['meta'][$key];
            $return = self::getOrderMetaByKey($key, $orderData['meta']);
        }
        $pluginPrefixedKey = sprintf('%s_%s', self::getPrefix(), $key);
        if (isset($orderData['meta'])) {
            $pluginReturn = self::getOrderMetaByKey($pluginPrefixedKey, $orderData['meta']);
            if (!empty($pluginReturn) && empty($return)) {
                $return = $pluginReturn;
            }
        }
        if ($key === 'resurspayment' && isset($orderData['ecom']) && is_object($orderData['ecom'])) {
            $return = $orderData['ecom'];
        }

        return $return ?? null;
    }

    /**
     * @param $suffixedKey
     * @param $orderDataMeta
     * @return mixed|null
     * @since 0.0.1.0
     */
    private static function getOrderMetaByKey($suffixedKey, $orderDataMeta)
    {
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
        return $return ?? null;
    }

    /**
     * Check if severity level is allowed before writing to log.
     * @param $eventType
     * @param $logData
     * @return bool
     * @since 0.0.1.0
     */
    public static function writeLogEvent($eventType, $logData): bool
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
            self::writeLogInfo($logData);
        }

        return $return;
    }

    /**
     * @param string $logMessage
     * @since 0.0.1.0
     */
    public static function writeLogInfo(string $logMessage): void
    {
        self::writeLogByLogLevel(
            logLevel: LogLevel::INFO,
            message: $logMessage
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
     * Allowing specific styles if admin is active.
     *
     * @since 0.0.1.1
     */
    public static function getSafeStyle()
    {
        add_filter('safe_style_css', function ($styles) {
            $styles[] = 'display';
            $styles[] = 'border';
            return $styles;
        });
    }

    /**
     * @return array
     * @since 0.0.1.6
     */
    public static function getSupportArray(): array
    {
        return (array)WordPress::applyFilters('getSupportAddressList', []);
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
            $mockOptionName = WordPress::getSnakeCase(sprintf('mock%s', ucfirst($specificMock)));
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
     * @param bool $isAdmin
     * @return array
     * @version 0.0.1.0
     */
    public static function getPluginScripts($isAdmin = null): array
    {
        if ($isAdmin) {
            $return = self::$jsLoadersAdmin;
        } else {
            $return = self::$jsLoaders;
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
     * Get the current proper payment method from session. This is vital information for the checkout.
     *
     * @return string
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getPaymentMethodBySession(): string
    {
        return (string)WooCommerce::getSessionValue('paymentMethod');
    }

    /**
     * @return string
     * @throws Exception
     * @since 0.0.1.8
     */
    public static function getMethodFromFragmentOrSession(): string
    {
        // If payment methods are changed during this session moment, it should be stored as a fragment
        // update. This value should be pushed out through the fragment update section to the front end
        // script so that the front-end script can see which payment method that is currently selected
        // and hide RCO if something else is active.

        // This method are sometimes executed with a payment method in its post-request, and therefore must
        // be processed before returning the value, since another value can be stored prior to this change.
        if (isset($_REQUEST['payment_method'])) {
            WooCommerce::setSessionValue('fragment_update_payment_method', $_REQUEST['payment_method']);
        }

        return (string)WooCommerce::getSessionValue('fragment_update_payment_method');
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
     * Get data from plugin setup block (top of init.php), like WP, but from within our own needs.
     *
     * @param $key
     * @return string
     * @version 0.0.1.0
     */
    private static function getPluginDataContent($key): string
    {
        $return = '';

        // Origin code borrowed from WP.
        if (file_exists(self::getPluginInitFile())) {
            $pluginContent = get_file_data(self::getPluginInitFile(), [$key => $key]);
            $return = $pluginContent[$key];
        }

        return $return;
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
     * @param $links
     * @param $file
     * @return array
     * @since 0.0.1.2
     */
    public static function getPluginRowMeta($links, $file): array
    {
        $row_meta = [];

        if (false !== strpos($file, WooCommerce::getBaseName())) {
            $urls = [
                __(
                    'Original Plugin Documentation',
                    'resurs-bank-payments-for-woocommerce'
                ) => 'https://docs.tornevall.net/x/CoC4Aw',
            ];
            if (self::isOriginalCodeBase()) {
                $urls[__('Github', 'resurs-bank-payments-for-woocommerce')] =
                    'https://github.com/Tornevall/tornevalls-resurs-bank-payment-gateway-for-woocommerce';
            }

            foreach ($urls as $urlInfo => $url) {
                $row_meta[$urlInfo] = sprintf(
                    '<a href="%s" title="%s" target="_blank">%s</a>',
                    esc_url($url),
                    esc_attr($urlInfo),
                    esc_html($urlInfo)
                );
            }
        }

        return array_merge($links, $row_meta);
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

        $aes = new Aes();
        switch ($aes->getCryptoLib()) {
            case AES::CRYPTO_SSL:
                $cryptoLibType = 'SSL/OpenSSL';
                break;
            case AES::CRYPTO_MCRYPT:
                $cryptoLibType = __(
                    'Your system has crypto support but currently you are using the deprecated module mcrypt for it. ' .
                    'It is strongly recommended to upgrade to a modern package as soon as possible.',
                    'resurs-bank-payments-for-woocommerce'
                );
                break;
            default:
                $cryptoLibType = __(
                    'Your system is missing support for crypto. This module may not work properly without it!',
                    'resurs-bank-payments-for-woocommerce'
                );
        }

        $renderData = [
            __(
                'Plugin version',
                'resurs-bank-payments-for-woocommerce'
            ) => esc_html(self::getCurrentVersion()),
            __(
                'Internal Release Prefix',
                'resurs-bank-payments-for-woocommerce'
            ) => esc_html(self::getPrefix()),
            __(
                'WooCommerce',
                'resurs-bank-payments-for-woocommerce'
            ) => sprintf(
                __(
                    '%s, at least %s are required.',
                    'resurs-bank-payments-for-woocommerce'
                ),
                esc_html(WooCommerce::getWooCommerceVersion()),
                esc_html(WooCommerce::getRequiredVersion())
            ),
            __(
                'PHP Version',
                'resurs-bank-payments-for-woocommerce'
            ) => PHP_VERSION,
            __(
                'Webservice Library',
                'resurs-bank-payments-for-woocommerce'
            ) => defined('ECOMPHP_VERSION') ? 'ecomphp-' . ECOMPHP_VERSION : '',
            __(
                'Communication Library',
                'resurs-bank-payments-for-woocommerce'
            ) => esc_html('netcurl-' . $netWrapper->getVersion()),
            __(
                'Communication Drivers',
                'resurs-bank-payments-for-woocommerce'
            ) => nl2br(Data::getEscapedHtml(implode("\n", self::getWrapperList($netWrapper)))),
            __(
                'Crypto Library',
                'resurs-bank-payments-for-woocommerce'
            ) => $cryptoLibType,
            __(
                'Network Lookup',
                'resurs-bank-payments-for-woocommerce'
            ) => '<div id="rbwcNetworkLookup">&nbsp;</div>',
        ];

        $content .= Data::getEscapedHtml(
            self::getGenericClass()->getTemplate(
                'plugin_information',
                [
                    'support_string' => self::getSpecialString('support_string'),
                    'render' => WordPress::applyFilters('renderInformationData', $renderData),
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
                'resurs-bank-payments-for-woocommerce'
            ),
            'support_string' => __(
                'If you ever need support with this plugin, you should primarily check this ' .
                'page before sending support requests. When you send the requests, make sure you do ' .
                'include the information below in your message. Doing this, it will be easier ' .
                'in the end to help you out.',
                'resurs-bank-payments-for-woocommerce'
            ),
        ];

        return $array[$key] ?? '';
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
            $return = self::base64urlEncode($data);
        }

        if ($dataEncryptionState instanceof Exception) {
            self::writeLogNotice(
                sprintf(
                    __(
                        '%s failed encryption (%d): %s. Failover to base64.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    __FUNCTION__,
                    $dataEncryptionState->getCode(),
                    $dataEncryptionState->getMessage()
                )
            );
        }

        return (string)$return;
    }

    /**
     * Base64-encoded data, but with URL-safe characters.
     * @param string $data
     * @return string
     * @todo Can we import this to ecom2?
     */
    public function base64urlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64-decoded data, but with URL-safe characters.
     * @param string $data
     * @return string
     * @todo Can we import this to ecom2?
     */
    public function base64urlDecode(string $data): string
    {
        return (string)base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
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
    public static function writeLogNotice($logMessage)
    {
        self::writeLogByLogLevel(
            LogLevel::INFO,
            $logMessage
        );
    }

    /**
     * @param string $data
     * @param bool $base64
     * @return mixed
     * @since 0.0.1.0
     * @noinspection BadExceptionsProcessingInspection
     */
    public static function getDecryptData($data = '', bool $base64 = false)
    {
        if (!isset($data)) {
            $data = '';
        }

        if (isset($data)) {
            try {
                if ($base64) {
                    $return = self::base64urlDecode($data);
                } else {
                    $crypt = self::getCrypt();
                    $return = $crypt->aesDecrypt($data);
                }
            } catch (Exception $e) {
                self::writeLogException($e, __FUNCTION__);
                $return = Data::base64urlDecode($data);
            }
        }

        return $return ?? $data;
    }

    /**
     * If plugin is enabled on admin level as a payment method.
     *
     * @return bool
     * @since 0.0.1.0
     * @todo There are several places that is still using this method instead of askign Enable:: directly.
     * @todo Remove this method and use Enable:: instead, where it still is in use.
     */
    public static function isEnabled()
    {
        return Enabled::isEnabled();
    }

    /**
     * @param $fromFunction
     * @param $message
     * @return bool
     * @since 0.0.1.0
     */
    public static function setDeveloperLog($fromFunction, $message): bool
    {
        return self::writeLogEvent(
            self::CAN_LOG_ORDER_DEVELOPER,
            sprintf(
                __(
                    'DevLog Method "%s" message: %s.',
                    'resurs-bank-payments-for-woocommerce'
                ),
                $fromFunction,
                $message
            )
        );
    }

    /**
     * @param $ecomObject
     * @return bool
     * @throws ResursException
     * @since 0.0.1.8
     */
    public static function canIgnoreFrozen($ecomObject): bool
    {
        // Apply hidden feature, if something go sideways.
        return (bool)Data::getOrderMeta(
            'ignore_frozen',
            $ecomObject
        );
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
        if (is_object($order) && method_exists($order, 'get_id')) {
            $orderId = $order->get_id();
        } elseif ((int)$order > 0) {
            $orderId = $order;
        }

        if (isset($orderId)) {
            self::writeLogEvent(
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
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    __FUNCTION__
                ),
                400
            );
        }

        return $return;
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
     * @since 0.0.1.8
     */
    public static function hasCredentials(): bool
    {
        return (ClientSecret::getData() !== '' && ClientId::getData() !== '');
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     * @todo Can probably be removed. Make sure it's done safely, when switching code to MAPI.
     */
    public static function getResolvedCredentials(): bool
    {
        $credentials = self::getResolvedCredentialData();

        if (empty($credentials['jwt_client_id']) || empty($credentials['jwt_client_secret'])) {
            throw new RuntimeException('ECom credentials are not fully set.', self::UNSET_CREDENTIALS_EXCEPTION);
        }

        return true;
    }

    /**
     * @return array
     * @since 0.0.1.8
     */
    public static function getResolvedCredentialData(): array
    {
        $environment = self::getResursOption('environment');

        if ($environment === 'live') {
            $getUserFrom = 'jwt_client_id_production';
            $getPasswordFrom = 'jwt_client_secret_production';
        } else {
            $getUserFrom = 'jwt_client_id';
            $getPasswordFrom = 'jwt_client_secret';
        }

        $credentials['jwt_client_id'] = self::getResursOption($getUserFrom);
        $credentials['jwt_client_secret'] = self::getResursOption($getPasswordFrom);

        return $credentials;
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
                        'resurs-bank-payments-for-woocommerce'
                    ),
                ]
            )
        );
    }

    /**
     * Selective string obfuscator for internal debugging.
     * Only call for this method when debugging since data will be rewritten.
     *
     * Since this feature is built for the logging function, obfuscation also means html entites applied to each
     * string, since the logging feature seems unable to handle utf8-transforming properly..
     *
     * @param $obfuscateThis
     * @param string $keyStart
     * @return array
     * @since 0.0.1.1
     * @todo Adapt this into the new ecom2 methods.
     */
    public static function getObfuscatedData($obfuscateThis, string $keyStart = 'rco_customer'): array
    {
        if (!Data::getResursOption('must_obfuscate_logged_personal_data')) {
            return $obfuscateThis;
        }
        $stringHandler = new Strings();
        if (isset($obfuscateThis[$keyStart])) {
            $obfuscationObject = $obfuscateThis[$keyStart];
            $lookFor = WordPress::applyFilters('getObfuscateLookupKeys', [
                'billingAddress',
                'deliveryAddress',
                'phone',
                'email',
            ]);
            foreach ($lookFor as $key) {
                if (isset($obfuscationObject[$key])) {
                    if (is_array($obfuscationObject[$key])) {
                        foreach ($obfuscationObject[$key] as $item => $value) {
                            $obfuscateThis[$keyStart][$key][$item] = htmlentities($stringHandler->getObfuscatedStringFull(
                                $value,
                                2,
                                0
                            ));
                        }
                    } elseif (is_string($obfuscationObject[$key])) {
                        $obfuscateThis[$keyStart][$key] = htmlentities(
                            $stringHandler->getObfuscatedStringFull(
                                $obfuscationObject[$key],
                                2,
                                0
                            )
                        );
                    }
                }
            }
        }

        foreach ($obfuscateThis as $item => $value) {
            if (is_string($value) &&
                ((bool)preg_match('/^billing_/i', $item) || (bool)preg_match('/^shipping_/i', $item))
            ) {
                $obfuscateThis[$item] = htmlentities($stringHandler->getObfuscatedStringFull(
                    $value,
                    2,
                    0
                ));
            }
        }

        return $obfuscateThis;
    }

    /**
     * @param $paymentMethod
     * @return string
     * @since 0.0.1.5
     */
    public static function getResursMethodFromPrefix($paymentMethod): string
    {
        $return = '';
        if (self::isResursMethod($paymentMethod)) {
            $return = (string)preg_replace(
                sprintf('/^%s_/', self::getPrefix()),
                '',
                $paymentMethod
            );
        }

        return $return;
    }

    /**
     * @return int
     */
    public static function getStoreId(): int
    {
        return (int)self::getResursOption('mapi_store_id');
    }
}
