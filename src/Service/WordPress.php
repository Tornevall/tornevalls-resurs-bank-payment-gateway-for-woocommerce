<?php

namespace ResursBank\Service;

use Exception;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use ResursBank\Gateway\ResursDefault;
use ResursBank\Module\Data;
use ResursBank\Module\ResursBankAPI;
use ResursBank\ResursBank\ResursPlugin;
use Resursbank\Woocommerce\Database\Options\Enabled;
use Resursbank\Woocommerce\Modules\GetAddress\Module as GetAddress;
use Resursbank\Woocommerce\Util\Admin;
use Resursbank\Woocommerce\Util\Metadata;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Url;
use RuntimeException;
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
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }

        // Register special routes (do not put this in init.php, but after WooCommerce init).
        // If executed in wrong order, the routes will instead crash the site (even from a plugins_loaded perspective).
        Route::exec();

        // Make sure Ecom2 is loaded as soon as possible.
        new ResursBankAPI();

        // Initialize adaptions.
        new ResursPlugin();

        GetAddress::setup();

        // Always initialize defaults once on plugin loaded (performance saver).
        self::adminGatewayRedirect();
        self::setupAjaxActions();
        self::setupFilters();
        self::setupScripts();
        self::setupActions();
        self::doAction('isLoaded', true);
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

        if ($_REQUEST['page'] === 'wc-settings' &&
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
        // Data calls.
        add_filter('rbwc_get_plugin_information', 'ResursBank\Module\Data::getPluginInformation');
        // Helper calls.
        add_filter('woocommerce_get_settings_pages', 'ResursBank\Service\WooCommerce::getSettingsPages');
        add_filter('is_protected_meta', 'ResursBank\Service\WooCommerce::getProtectedMetaData', 10, 3);

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
        add_action('admin_notices', '\ResursBank\Service\WordPress::getAdminNotices');
        add_action('rbwc_get_localized_scripts', '\ResursBank\Service\WordPress::getLocalizedScripts', 10, 3);
        add_action('rbwc_localizations_admin', '\ResursBank\Service\WordPress::getLocalizedScriptsDeprecated', 10, 2);
        add_action('wp_ajax_' . $action, '\ResursBank\Module\PluginApi::execApi');
        add_action(
            'woocommerce_single_product_summary',
            'Resursbank\Woocommerce\Modules\PartPayment\Module::getWidget'
        );
        // Using woocomerce_thankyou rather than woocommerce_thankyou_<id> as we run dynamic methods.
        add_action('woocommerce_thankyou', 'ResursBank\Module\OrderStatus::setOrderStatusOnThankYouSuccess');

        add_action('add_meta_boxes', 'ResursBank\Service\WordPress::getMetaBoxes', 10);
    }

    /**
     * Order data meta box for Resurs.
     */
    public static function getMetaBoxes(): void
    {
        global $post;

        // Validate the order as a Resurs belonging before starting to throw meta-boxes at the order.
        if (isset($post) &&
            $post instanceof WP_Post &&
            Metadata::isValidResursPayment(order: new WC_Order($post->ID))
        ) {
            $screen = get_current_screen();
            $screen_id = $screen ? $screen->id : '';

            if ($screen_id === 'shop_order') {
                add_meta_box(
                    'resursbank_orderinfo',
                    sprintf(
                        __('%s order information', 'resurs-bank-payments-for-woocommerce'),
                        'Resurs'
                    ),
                    'ResursBank\Module\OrderMetaBox::output_order'
                );
                add_meta_box(
                    'resursbank_order_meta_details',
                    sprintf(
                        __('%s order meta data', 'resurs-bank-payments-for-woocommerce'),
                        'Resurs'
                    ),
                    'ResursBank\Module\OrderMetaBox::output_meta_details'
                );
            }
        }
    }

    /**
     * Look for admin notices.
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getAdminNotices()
    {
        global $current_tab, $parent_file;

        // See if there is a credential error for Resurs Bank.
        self::getCredentialError();

        $internalExceptions = self::applyFilters(
            'getPluginAdminNotices',
            (isset($_SESSION[Data::getPrefix()]['exception']) ? $_SESSION[Data::getPrefix()]['exception'] : [])
        );

        if (count($internalExceptions)) {
            $class = 'notice notice-error is-dismissible';
            /** @noinspection PhpUnusedLocalVariableInspection */
            foreach ($internalExceptions as $index => $item) {
                printf(
                    '<div class="%1$s"><p>[%3$s] %2$s</p></div>',
                    esc_attr($class),
                    esc_html($item->getMessage()),
                    Data::getPrefix()
                );
            }
            if (isset($_SESSION[Data::getPrefix()]['exception'])) {
                unset($_SESSION[Data::getPrefix()]['exception']);
            }
        }

        $requiredVersionNotice = sprintf(
            __(
                'The current plugin requires at least version %s - for the moment, you are running ' .
                'on version %s. You should consider upgrading as soon as possible.',
                'resurs-bank-payments-for-woocommerce'
            ),
            WooCommerce::getRequiredVersion(),
            WooCommerce::getWooCommerceVersion()
        );

        if ($parent_file === 'woocommerce') {
            try {
                WooCommerce::testRequiredVersion(false);
            } catch (Exception $e) {
                Data::writeLogException($e, __FUNCTION__);
                // @todo Rewrite this section to warn about lowest requirement of WooCommerce if current installation
                // @todo is below that version. This code is temporarily changed to release the needs of the
                // @todo Generic-class-renderer, and need to be fixed.
                echo Data::getEscapedHtml(
                    content: '<div class="notice notice-error is-dismissible" style="font-weight: bold; color: #6c0c0c; background: #fac5c5;">
                      <p>' . $requiredVersionNotice . '</p></div>
            ');
            }
        }
    }

    /**
     * Generate admin notices the ugly way since there is no proper front end script to push
     * out such notices.
     * @since 0.0.1.0
     */
    private static function getCredentialError()
    {
        $frontCredentialCheck = Data::getResursOption('front_callbacks_credential_error');
        try {
            if (!empty($frontCredentialCheck)) {
                $credentialMessage = json_decode($frontCredentialCheck, false);
                // Generate an exception the ugly way.
                if (isset($credentialMessage->message)) {
                    throw new RuntimeException(
                        sprintf(
                            'Resurs Bank %s (%s): %s',
                            $credentialMessage->function ?? __FUNCTION__,
                            $credentialMessage->code,
                            $credentialMessage->message
                        ),
                        $credentialMessage->code
                    );
                }
            }
        } catch (Exception $e) {
            self::setGenericError($e);
        }
    }

    /**
     * @param Throwable|Exception $exception
     * @since 0.0.1.4
     */
    public static function setGenericError(Throwable|Exception $exception)
    {
        if (!isset($_SESSION[ResursDefault::PREFIX]['exception'])) {
            $_SESSION[ResursDefault::PREFIX]['exception'] = [];
        }
        // Make sure the errors are not duplicated.
        if (self::canAddException(exception: $exception)) {
            // Add the exception to the session variable since that's where we can give it to WordPress in
            // the easiest way on page reloads/changes.
            $_SESSION[ResursDefault::PREFIX]['exception'][] = $exception;
        }
    }

    /**
     * Look for duplicate messages in session exceptions.
     *
     * @param Throwable|Exception $exception
     * @return bool
     * @since 0.0.1.4
     */
    private static function canAddException(Throwable|Exception $exception): bool
    {
        $return = true;

        if (isset($_SESSION[Data::getPrefix()]['exception'])) {
            /** @var Exception $item */
            foreach ($_SESSION[Data::getPrefix()]['exception'] as $exceptionItem) {
                if ($exceptionItem instanceof Exception) {
                    $message = $exceptionItem->getMessage();
                    if ($exception->getMessage() === $message) {
                        $return = false;
                        break;
                    }
                }
            }
        } else {
            $return = false;
        }

        return $return;
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
        // Note: This section used to be called with a prefix to define which version of the plugin
        // we use. However, as there's a static setup in the front-end section, the prefix can not in this
        // case be used without having problems loading scripts. So: Do not use dynamic prefixes in this
        // autoloader.
        foreach (Data::getPluginStyles($isAdmin) as $styleName => $styleFile) {
            wp_enqueue_style(
                sprintf('trbwc_%s', $styleName),
                sprintf(
                    '%s/css/%s?%s',
                    Data::getGatewayUrl(),
                    $styleFile,
                    Data::getTestMode() ? time() : 'static'
                ),
                [],
                Data::getCurrentVersion()
            );
        }

        foreach (Data::getPluginScripts($isAdmin) as $scriptName => $scriptFile) {
            $realScriptName = sprintf('trbwc_%s', $scriptName);
            self::setEnqueue($realScriptName, $scriptFile, $isAdmin);
        }

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
     * @param $scriptName
     * @param $scriptFile
     * @param $isAdmin
     * @param array $localizeArray
     * @since 0.0.1.0
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    public static function setEnqueue($scriptName, $scriptFile, $isAdmin, $localizeArray = [])
    {
        wp_enqueue_script(
            $scriptName,
            sprintf(
                '%s/js/%s?%s',
                Data::getGatewayUrl(),
                $scriptFile,
                Data::getTestMode() ? Data::getPrefix() . '-' . time() : 'static'
            ),
            Data::getJsDependencies($scriptName, $isAdmin)
        );
        self::doAction('getLocalizedScripts', $scriptName, $isAdmin, $localizeArray);
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
        $return['prefix'] = Data::getPrefix();
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
        return Data::getPrefix($tag) . '|' . ($strictify ? $_SERVER['REMOTE_ADDR'] : '');
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
        Data::writeLogEvent(
            Data::CAN_LOG_JUNK,
            sprintf(
                __(
                    'Apply filter: %s',
                    'resurs-bank-payments-for-woocommerce'
                ),
                $filterName
            )
        );

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

        if (!empty(Data::getResursOptionDeprecated('login'))) {
            $importDeprecated = (int)Data::getResursOption('resursImportCredentials');

            $return['deprecated_unixtime'] = $importDeprecated;
            $return['deprecated_timestamp'] = Date('Y-m-d, H:i', $importDeprecated);
            $return['can_import_deprecated_credentials'] = $importDeprecated === 0;
            $return['imported_credentials'] = __(
                'Credentials was imported from an older platform',
                'resurs-bank-payments-for-woocommerce'
            );

            if (!$importDeprecated) {
                $return['resurs_deprecated_credentials'] = __(
                    'Import credentials from Resurs v2.x',
                    'resurs-bank-payments-for-woocommerce'
                );
                $return['credential_import_success'] = __(
                    'Import successful.',
                    'resurs-bank-payments-for-woocommerce'
                );
                $return['credential_import_failed'] = __(
                    'Import failed.',
                    'resurs-bank-payments-for-woocommerce'
                );
            }
        }
        return $return;
    }
}
