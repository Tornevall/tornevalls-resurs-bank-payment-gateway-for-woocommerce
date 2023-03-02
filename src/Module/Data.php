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
use Resursbank\Woocommerce\Util\Translator;
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
}
