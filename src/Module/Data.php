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
use Resursbank\Woocommerce\Database\Options\Api\ClientId;
use Resursbank\Woocommerce\Database\Options\Api\ClientSecret;
use Resursbank\Woocommerce\Database\Options\Api\Enabled;
use Resursbank\Woocommerce\Util\Metadata;
use RuntimeException;
use WC_Customer;
use WC_Order;
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
     * @var array $fileImageExtensions
     * @since 0.0.1.0
     */
    private static $fileImageExtensions = ['jpg', 'gif', 'png', 'svg'];

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
        if (
            $hasExtension && (bool)preg_match(
                sprintf('/^(.*?)(.%s)$/', implode('|.', self::$fileImageExtensions)),
                $imageFile
            )
        ) {
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
     * @since 0.0.1.6
     */
    public static function isOriginalCodeBase(): bool
    {
        return WooCommerce::getBaseName() === 'resurs-bank-payments-for-woocommerce';
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
     * Centralized escaper for internal templates.
     *
     * @param $content
     * @return string
     * @since 0.0.1.1
     */
    public static function getEscapedHtml($content): string
    {
        return wp_kses(
            string: $content,
            allowed_html: self::getSafeTags()
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
                'colspan' => []
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
            ]
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
     * If plugin is enabled on admin level as a payment method.
     *
     * @return bool
     * @todo There are several places that is still using this method instead of askign Enable:: directly.
     * @todo Remove this method and use Enable:: instead, where it still is in use.
     */
    public static function isEnabled()
    {
        return Enabled::isEnabled();
    }
}
