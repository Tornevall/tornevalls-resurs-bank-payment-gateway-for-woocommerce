<?php

namespace ResursBank\Service;

use Automattic\WooCommerce\Admin\PageController;
use Exception;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use ResursBank\Module\Data;
use ResursBank\ResursBank\ResursPlugin;
use Resursbank\Woocommerce\Database\Options\Enabled;
use Resursbank\Woocommerce\Modules\Api\Connection;
use Resursbank\Woocommerce\Modules\CustomerType\Filter\CustomerType;
use Resursbank\Woocommerce\Modules\Gateway\ResursDefault;
use Resursbank\Woocommerce\Modules\GetAddress\Module as GetAddress;
use Resursbank\Woocommerce\SettingsPage;
use Resursbank\Woocommerce\Util\Admin;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Url;
use Throwable;
use WC_Order;
use WP_Post;

use function count;
use function defined;
use function func_get_args;
use function is_array;

/**
 * Class WordPress related actions.
 *
 * @package ResursBank
 * @since 0.0.1.0
 */
class WordPress
{
    /**
     * @since 0.0.1.0
     */
    public static function initializeWooCommerce()
    {
        // Do not actively work where WooCommerce isn't live.
        if (!class_exists(class: 'WC_Payment_Gateway')) {
            return;
        }

        // Register special routes (do not put this in init.php, but after WooCommerce init).
        // If executed in wrong order, the routes will instead crash the site (even from a plugins_loaded perspective).
        Route::exec();

        GetAddress::setup();
        CustomerType::setup();

        // Always initialize defaults once on plugin loaded (performance saver).
        self::adminGatewayRedirect();
        self::setupAjaxActions();
        self::setupFilters();
        self::setupScripts();
        self::setupActions();
        self::doAction(actionName: 'isLoaded', value: true);
    }

    /**
     * Make sure redirects from WooCommerce-tab payments goes the right way, when button is clicked.
     * Currently, this plugin is not located (due to how we want to configure it) in regular WooCommerce-tabs
     * so this redirect must be handled this way.
     *
     * @return void
     */
    private static function adminGatewayRedirect(): void
    {
        // No action on wrong request variables.
        if (!isset($_REQUEST['page']) || !isset($_REQUEST['tab']) || !isset($_REQUEST['section'])) {
            return;
        }

        if (
            $_REQUEST['page'] === 'wc-settings' &&
            $_REQUEST['tab'] === 'checkout' &&
            $_REQUEST['section'] === (new ResursDefault())->id
        ) {
            wp_redirect(admin_url('admin.php?page=wc-settings&tab=resursbank&section=api_settings'));
            exit;
        }
    }

    /**
     * AJAX action validation. Without this check, AJAX calls to the plugin handler is not allowed.
     *
     * @since 0.0.1.0
     */
    private static function setupAjaxActions()
    {
        // Take a note on checkout_create_order, which is breaking some kind of internal standard here.
        // The intentions from the beginning was to just use an rco-naming here.
        $actionList = [
            'test_credentials',
            'import_credentials',
            'get_payment_methods',
            'get_new_callbacks',
            'get_trigger_test',
            'get_trigger_response',
            'get_address',
            'checkout_create_order',
            'purchase_reject',
            'callback_unregister',
            'get_callback_matches',
            'get_callback_matches',
            'get_internal_resynch',
            'set_new_annuity',
            'get_new_annuity_calculation',
            'get_cost_of_purchase',
            'get_network_lookup',
            'reset_plugin_settings',
            'reset_old_plugin_settings',
            'update_payment_method_description',
            'update_payment_method_fee',
            'set_method_state',
        ];

        foreach ($actionList as $action) {
            $camelCaseAction = sprintf('ResursBank\Module\PluginApi::%s', self::getCamelCase($action));
            add_action(
                sprintf('rbwc_%s', $action),
                $camelCaseAction
            );
        }
    }

    /**
     * Internal filter setup.
     *
     * @since 0.0.1.0
     */
    private static function setupFilters()
    {
        // Generic calls.
        add_filter('plugin_action_links', 'ResursBank\Service\WooCommerce::getPluginAdminUrl', 10, 2);
        add_filter('plugin_row_meta', 'ResursBank\Module\Data::getPluginRowMeta', 10, 2);

        if (Enabled::isEnabled()) {
            // Get list of current payment gateways (for admin parts).
            // @see https://rudrastyh.com/woocommerce/get-and-hook-payment-gateways.html
            add_filter('woocommerce_payment_gateways', 'ResursBank\Service\WooCommerce::getGateways');

            // Get list of available gateways (for checkout).
            // @see https://rudrastyh.com/woocommerce/get-and-hook-payment-gateways.html
            add_filter(
                'woocommerce_available_payment_gateways',
                'ResursBank\Service\WooCommerce::getAvailableGateways'
            );
        }
    }

    /**
     * Script preparation.
     *
     * @since 0.0.1.0
     */
    private static function setupScripts()
    {
        add_action('wp_enqueue_scripts', 'ResursBank\Service\WordPress::setResursBankScripts');
        add_action(
            'wp_head',
            'Resursbank\Woocommerce\Modules\UniqueSellingPoint\Module::setCss'
        );
        add_action(
            'wp_head',
            'Resursbank\Woocommerce\Modules\PartPayment\Module::setCss'
        );
        add_action(
            'wp_enqueue_scripts',
            'Resursbank\Woocommerce\Modules\PartPayment\Module::setJs'
        );
        if (Admin::isAdmin()) {
            add_action(
                'admin_enqueue_scripts',
                'Resursbank\Woocommerce\Modules\PartPayment\Admin::setJs'
            );
        }

        add_action(
            'admin_head',
            'Resursbank\Woocommerce\Modules\PaymentInformation\Module::setCss'
        );

        add_action('admin_enqueue_scripts', 'ResursBank\Service\WordPress::setResursBankScriptsAdmin');
    }

    /**
     * Basic actions.
     *
     * @since 0.0.1.0
     */
    private static function setupActions()
    {
        $action = Url::getRequest('action');
        add_action('rbwc_get_localized_scripts', '\ResursBank\Service\WordPress::getLocalizedScripts', 10, 3);
        add_action('rbwc_localizations_admin', '\ResursBank\Service\WordPress::getLocalizedScriptsDeprecated', 10, 2);
        add_action('wp_ajax_' . $action, '\ResursBank\Module\PluginApi::execApi');
        add_action(
            'woocommerce_single_product_summary',
            'Resursbank\Woocommerce\Modules\PartPayment\Module::getWidget'
        );
        add_action('updated_option', 'Resursbank\Woocommerce\Settings\PartPayment::validateLimit', 10, 3);
        add_action('add_meta_boxes', 'ResursBank\Service\WordPress::getMetaBoxes', 10);
    }

    /**
     * Order data meta box for Resurs.
     */
    public static function getMetaBoxes(): void
    {
        global $post;

        // Can't set up this check at the action registration point, so we have to check the post type
        // on the fly here, making sure that Resurs meta boxes are only active in the order view.
        if (isset($post) && $post instanceof WP_Post && $post->post_type === 'shop_order') {
            try {
                $validResursPayment = Metadata::isValidResursPayment(order: new WC_Order(order: $post->ID));
            } catch (Throwable) {
                $validResursPayment = false;
            }

            // Validate the order as a Resurs belonging before starting to throw meta-boxes at the order.
            if ($validResursPayment) {
                if ((new PageController())->get_current_screen_id() === 'shop_order') {
                    add_meta_box(
                        'resursbank_orderinfo',
                        sprintf(
                            __('%s order information', 'resurs-bank-payments-for-woocommerce'),
                            'Resurs'
                        ),
                        'ResursBank\Module\OrderMetaBox::output_order'
                    );
                }
            }
        }
    }

    /**
     * @param $filterName
     * @return string
     * @since 0.0.1.0
     */
    public static function getFilterName($filterName): string
    {
        $return = $filterName;

        if (defined(constant_name: 'RESURSBANK_SNAKE_CASE_FILTERS')) {
            $return = self::getSnakeCase($filterName);
        }

        return $return;
    }

    /**
     * Temporarily moved snake_case-converter from ecom1 to here.
     * @param $string
     * @return string
     * @todo We can either push this up to ecom2, or we can just drop this requirement entirely when we reach
     * @todo that point.
     */
    public static function getSnakeCase($string): string
    {
        $return = preg_split('/(?=[A-Z])/', $string);

        if (is_array($return)) {
            $return = implode('_', array_map('strtolower', $return));
        }

        return (string)$return;
    }

    /**
     * Temporarily moved camelCase-converter from ecom1 to here.
     * @param $string
     * @return string
     * @todo We can either push this up to ecom2, or we can just drop this requirement entirely when we reach
     * @todo that point.
     */
    public static function getCamelCase($string): string
    {
        return lcfirst(implode(@array_map("ucfirst", preg_split('/-|_|\s+/', $string))));
    }


    /**
     * Clean up arguments and return the real ones.
     *
     * @param $args
     * @return array
     * @since 0.0.1.0
     */
    public static function getFilterArgs($args): array
    {
        if (is_array($args) && count($args) > 2) {
            array_shift($args);
            array_shift($args);
        }

        return $args;
    }

    /**
     * @since 0.0.1.0
     */
    public static function setResursBankScriptsAdmin()
    {
        self::setResursBankScripts(true);
    }

    /**
     * @param null $isAdmin
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function setResursBankScripts($isAdmin = null)
    {
        if (Url::getRequest('action') === 'resursbank_get_cost_of_purchase') {
            $wooCommerceStyleSheet = get_stylesheet_directory_uri() . '/css/woocommerce.css';
            $resursStyleSheet = Data::getGatewayUrl() . '/css/costofpurchase.css';

            wp_enqueue_style(
                'woocommerce_default_style',
                $wooCommerceStyleSheet
            );
            wp_enqueue_style(
                'resurs_annuity_style',
                $resursStyleSheet
            );
        }
    }

    /**
     * @param $actionName
     * @param $value
     * @since 0.0.1.0
     */
    public static function doAction($actionName, $value)
    {
        $actionArray = [
            sprintf(
                '%s_%s',
                'rbwc',
                self::getFilterName($actionName)
            ),
            $value,
        ];

        do_action(...array_merge($actionArray, self::getFilterArgs(func_get_args())));
    }

    /**
     * @param $scriptName
     * @param null $isAdmin
     * @param null $extraLocalizationData
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getLocalizedScripts($scriptName, $isAdmin = null, $extraLocalizationData = null)
    {
        $localizationData = self::getLocalizationData($scriptName, (bool)$isAdmin);
        if ($localizationData) {
            if (is_array($extraLocalizationData) && count($extraLocalizationData)) {
                $localizationData = array_merge($localizationData, $extraLocalizationData);
            }
            wp_localize_script(
                $scriptName,
                sprintf('l_%s', $scriptName),
                $localizationData
            );
        }
    }

    /**
     * @param $scriptName
     * @param $isAdmin
     * @return array
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function getLocalizationData($scriptName, $isAdmin): array
    {
        $return = [];

        if ((bool)$isAdmin && preg_match('/_admin$/', $scriptName)) {
            $return = self::getLocalizationDataAdmin($return);
        } elseif (preg_match('/_all$/', $scriptName)) {
            $return = self::getLocalizationDataGlobal($return);
        } else {
            $return = self::getLocalizationDataGeneric($return, $scriptName);
        }

        return $return;
    }

    /**
     * Localized variables shown in admin only.
     *
     * @param $return
     * @return mixed
     * @throws ConfigException
     * @since 0.0.1.0
     */
    private static function getLocalizationDataAdmin($return)
    {
        global $current_tab, $current_section;
        $return['prefix'] = RESURSBANK_MODULE_PREFIX;
        $return['current_section'] = $current_section;
        //$return['noncify'] = self::getNonce('admin');
        $return['environment'] = Config::isProduction() ? 'prod' : 'test';
        $return['translate_checkout_simplified'] = __(
            'The integrated checkout (also known as the "simplified shop flow") is a direct integration with ' .
            'WooCommerce which uses intended APIs to interact with your customers while finishing the orders.',
            'resurs-bank-payments-for-woocommerce'
        );
        $return['translate_checkout_hosted'] = __(
            '"Resurs Hosted Checkout" works similarly as the integrated simplified checkout, but on the ' .
            'checkout itself the customer are redirected to a hosted website to fulfill their payments. ' .
            'It can be quite easily compared with a Paypal solution.',
            'resurs-bank-payments-for-woocommerce'
        );
        $return['resurs_test_credentials'] = __(
            'Validate and save credentials',
            'resurs-bank-payments-for-woocommerce'
        );
        $return['credential_failure_notice'] = __(
            'The credential check failed. If you save the current data we can not guarantee ' .
            'that your store will properly work.',
            'resurs-bank-payments-for-woocommerce'
        );
        $return['credential_success_notice'] = __(
            'The credential check was successful. You may now save the rest of your data.',
            'resurs-bank-payments-for-woocommerce'
        );
        $return['requireFraudControl'] = __(
            'This setting requires you to enable the fraud control.',
            'resurs-bank-payments-for-woocommerce'
        );
        $return['waiting_for_callback'] = __(
            'Waiting for test callback to arrive.',
            'resurs-bank-payments-for-woocommerce'
        );
        $return['trigger_test_fail'] = __(
            'Callback trigger is currently not working.',
            'resurs-bank-payments-for-woocommerce'
        );
        $return['callback_test_timeout'] = __(
            'Callback trigger timeout. Aborted.',
            'resurs-bank-payments-for-woocommerce'
        );
        $return['remove_callback_confirm'] = __(
            'Are you sure you want to remove callback',
            'resurs-bank-payments-for-woocommerce'
        );
        $return['update_callbacks_required'] = __(
            'Callback URLs for Resurs Bank may be outdated. Do you want to refresh the current data?',
            'resurs-bank-payments-for-woocommerce'
        );
        $return['update_callbacks_refresh'] = __(
            'Refresh has finished. Please check your new settings to confirm the update.',
            'resurs-bank-payments-for-woocommerce'
        );
        $return['current_tab'] = $current_tab;
        $return['enable'] = __('Enable', 'resurs-bank-payments-for-woocommerce');
        $return['disable'] = __('Disable', 'resurs-bank-payments-for-woocommerce');
        $return['cleanup_warning'] = __(
            'By accepting this, all your settings for this plugin will be restored to the absolute defaults. ' .
            'The only thing that will be kept intact is encryption keys, so that you do not loose access to ' .
            'prior order data if they exist encrypted. Are you sure?',
            'resurs-bank-payments-for-woocommerce'
        );
        $return['old_cleanup_warning'] = __(
            'This will remove all v2.2-based settings from the database. Are you sure?',
            'resurs-bank-payments-for-woocommerce'
        );
        $return['cleanup_reload'] = __(
            'Settings has been restored to default values. You may now reconfigure this plugin.',
            'resurs-bank-payments-for-woocommerce'
        );
        $return['cleanup_failed'] = __(
            'Plugin configuration reset failed. You may want to reload the page and try again.',
            'resurs-bank-payments-for-woocommerce'
        );
        $return['old_cleanup_failed'] = __(
            'Cleanup of v2.2 data failed. You may want to reload the page and try again.',
            'resurs-bank-payments-for-woocommerce'
        );
        $return['cleanup_aborted'] = __(
            'Plugin configuration reset has been cancelled.',
            'resurs-bank-payments-for-woocommerce'
        );
        $return['old_cleanup_aborted'] = __(
            'Cleanup of v2.2 data aborted.',
            'resurs-bank-payments-for-woocommerce'
        );
        $return['method_state_change_failure'] = __(
            'Failed change payment method state.',
            'resurs-bank-payments-for-woocommerce'
        );

        return self::applyFilters('localizationsAdmin', $return);
    }

    /**
     * Makes nonces strict based on client ip address.
     *
     * @param $tag
     * @param bool $strictify
     * @return string
     * @since 0.0.1.0
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    private static function getNonce($tag, $strictify = true): string
    {
        return (string)wp_create_nonce(self::getNonceTag($tag, $strictify));
    }

    /**
     * @param $tag
     * @param bool $strictify
     * @return string
     * @since 0.0.1.0
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    public static function getNonceTag($tag, $strictify = true): string
    {
        return RESURSBANK_MODULE_PREFIX . '|' . $tag . '|' . ($strictify ? $_SERVER['REMOTE_ADDR'] : '');
    }

    /**
     * WordPress equivalent for apply_filters, but properly prefixed with plugin name tag.
     *
     * @param $filterName
     * @param $value
     * @return mixed
     * @since 0.0.1.0
     */
    public static function applyFilters($filterName, $value)
    {
        $applyArray = [
            sprintf(
                '%s_%s',
                'rbwc',
                self::getFilterName($filterName)
            ),
            $value,
        ];

        return apply_filters(...array_merge($applyArray, self::getFilterArgs(func_get_args())));
    }

    /**
     * Localized variables shown in all views.
     *
     * @param $return
     * @return mixed
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function getLocalizationDataGlobal($return)
    {
        // Set timeout to one second more than the backend timeout.
        $defaultTimeout = ((Data::getDefaultApiTimeout() + 1) * 1000);
        $setAjaxifyTimeout = self::applyFilters('ajaxifyTimeout', $defaultTimeout);
        //$return['noncify'] = self::getNonce('all');
        //$return['ajaxify'] = admin_url('admin-ajax.php');
        //$return['ajaxifyTimeout'] = (int)$setAjaxifyTimeout ? $setAjaxifyTimeout : $defaultTimeout;
        //$return['spin'] = WordPress::applyFilters('getImageSpinner', Data::getImage('spin.gif'));
        $return['success'] = __('Successful.', 'resurs-bank-payments-for-woocommerce');
        $return['failed'] = __('Failed.', 'resurs-bank-payments-for-woocommerce');
        $return['fragmethod'] = Data::getMethodFromFragmentOrSession();

        $return['reloading'] = __(
            'Please wait while reloading...',
            'resurs-bank-payments-for-woocommerce'
        );
        $return['nonce_error'] = __(
            'The page security (nonce) is reportedly expired or wrong. This can also be caused by the ' .
            'fact that you have already interacted with the page you are trying to update information on. ' .
            'You may want to reload your browser before proceeding.',
            'resurs-bank-payments-for-woocommerce'
        );

        return self::applyFilters('localizationsGlobal', $return);
    }

    /**
     * Localized variables shown in front (customer) view only.
     *
     * @param $return
     * @param null $scriptName
     * @return mixed
     * @since 0.0.1.0
     */
    private static function getLocalizationDataGeneric($return, $scriptName = null)
    {
        $return['noncify'] = self::getNonce('simple');

        return self::applyFilters('localizationsGeneric', $return, $scriptName);
    }

    /**
     * @param $return
     * @return mixed
     * @since 0.0.1.0
     */
    public static function getLocalizedScriptsDeprecated($return)
    {
        // Setting defaults.
        $return['can_import_deprecated_credentials'] = false;
        $return['deprecated_unixtime'] = 0;

        return $return;
    }
}
