<?php

/** @noinspection PhpUndefinedFieldInspection */

/** @noinspection ParameterDefaultValueIsNotNullInspection */

namespace ResursBank\Service;

use Exception;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Lib\Model\PaymentMethod;
use Resursbank\Ecom\Module\PaymentMethod\Repository as PaymentMethodRepository;
use ResursBank\Module\Data;
use ResursBank\Module\PluginHooks;
use Resursbank\Woocommerce\Database\Options\Advanced\StoreId;
use Resursbank\Woocommerce\Modules\Gateway\ResursDefault;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\SettingsPage;
use Resursbank\Woocommerce\Util\Url;
use RuntimeException;
use stdClass;
use Throwable;
use WC_Order;
use WC_Product;
use function count;
use function in_array;
use function is_array;
use function is_object;
use function is_string;

/**
 * Class WooCommerce related actions.
 *
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
     *
     * @var string
     * @since 0.0.1.0
     */
    private static $requiredVersion = '3.5.0';

    /**
     * Return the active state of this plugin based on preloaded plugins.
     * If WooCommerce is not found in the current list of WP-plugins, this
     * feature will disable this plugin too.
     * @return bool
     */
    public static function getActiveState(): bool
    {
        return in_array(
            needle: 'woocommerce/woocommerce.php',
            haystack: apply_filters(hook_name: 'active_plugins', value: get_option(option: 'active_plugins')),
            strict: true
        );
    }

    /**
     * Get available gateways (MAPI).
     * @param mixed $gateways
     * @return mixed
     * @throws ConfigException
     * @throws Throwable
     * @todo Move.
     */
    public static function getAvailableGateways(mixed $gateways): mixed
    {
        if (is_admin()) {
            return $gateways;
        }

        if (is_array(value: $gateways)) {
            // Payment methods here are listed for non-admin-pages only. In admin, the only gateway visible
            // should be ResursDefault in its default state.
            try {
                $gateways += WooCommerce::getGatewaysFromPaymentMethods(gateways: $gateways);
            } catch (Throwable $e) {
                // Catch errors if something goes wrong during gateway fetching.
                // If errors occurs in wp-admin, an error note will show up, instead of crashing the entire site.
                MessageBag::addError(msg: 'Failed to get list of gateways.');
                Config::getLogger()->error(message: $e);
            }
        }

        return $gateways;
    }

    /**
     * Get list of all gateways regardless of availability (MAPI).
     * @param mixed $gateways
     * @return mixed
     * @see https://rudrastyh.com/woocommerce/get-and-hook-payment-gateways.html
     * @todo Move.
     */
    public static function getGateways(mixed $gateways): mixed
    {
        if (is_array(value: $gateways)) {
            $gateways[] = ResursDefault::class;
        }

        return $gateways;
    }

    /**
     * Handle payment methods as separate gateways without the necessary steps to have separate classes on disk
     * or written in database.
     *
     * @param array $gateways
     * @return array
     * @throws ConfigException
     * @todo Move.
     */
    private static function getGatewaysFromPaymentMethods(array $gateways = []): array
    {
        try {
            $paymentMethodList = PaymentMethodRepository::getPaymentMethods(StoreId::getData());

            /** @var PaymentMethod $paymentMethod */
            foreach ($paymentMethodList as $paymentMethod) {
                $gateway = new ResursDefault(resursPaymentMethod: $paymentMethod);
                if ($gateway->is_available()) {
                    $gateways[RESURSBANK_MODULE_PREFIX . '_' . $paymentMethod->id] = $gateway;
                }
            }
        } catch (Exception $e) {
            // If we run the above request live, when the APIs are down, we want to catch the exception silently
            // or the site will break. If we are located in admin, we also want to visualize the exception as
            // a message not a crash.
            MessageBag::addError(msg: 'Failed to apply payment gateways.');
            Config::getLogger()->error(message: $e);
        }

        // If request failed or something caused an empty result, we should still return the list of gateways as
        // gateways. Have in mind that this array may already have content from other plugins.
        return $gateways;
    }

    /**
     * wp-admin plugin handler url-maker. Requested from filters that creates links that
     * resides under the plugin information.
     *
     * @param $links
     * @param $file
     * @param null $section
     * @return mixed
     * @noinspection PhpUnused
     * @todo Fix and move.
     */
    public static function getPluginAdminUrl($links, $file, $section = null): mixed
    {
        if (str_contains(haystack: $file, needle: self::getBaseName())) {
            /** @noinspection HtmlUnknownTarget */
            $links[] = sprintf(
                '<a href="%s?page=wc-settings&tab=%s&section=api_settings">%s</a>',
                admin_url('admin.php'),
                RESURSBANK_MODULE_PREFIX,
                'Settings'
            );
        }
        return $links;
    }

    /**
     * @return string
     * @since 0.0.1.0
     * @todo Fix and move or remove and make this request independent.
     */
    public static function getBaseName(): string
    {
        if (empty(self::$basename)) {
            self::$basename = trim(string: plugin_basename(file: Data::getGatewayPath()));
        }

        return self::$basename;
    }
}
