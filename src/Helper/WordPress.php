<?php

namespace ResursBank\Helper;

use Exception;
use ResursBank\Module\Api;
use ResursBank\Module\Data;
use ResursBank\Module\FormFields;
use TorneLIB\IO\Data\Strings;

/**
 * Class WordPress WordPress related actions.
 *
 * @package ResursBank
 * @since 0.0.1.0
 */
class WordPress
{
    /**
     * @since 0.0.1.0
     */
    public static function initializePlugin()
    {
        // Do not actively work where WooCommerce isn't live.
        if (!class_exists('WC_Payment_Gateway')) {
            return;
        }

        self::setupAjaxActions();
        self::setupFilters();
        self::setupScripts();
        self::setupActions();
        self::setupWoocommerceAdminActions();
        self::setupWoocommerceCheckoutActions();
    }

    /**
     * Preparing for ajax actions.
     *
     * @since 0.0.1.0
     */
    private static function setupAjaxActions()
    {
        $actionList = [
            'test_credentials',
            'import_credentials',
            'get_payment_methods',
            'get_new_callbacks',
            'get_trigger_test',
        ];

        foreach ($actionList as $action) {
            $camelCaseAction = sprintf('ResursBank\Module\PluginApi::%s', Strings::returnCamelCase($action));
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
        add_filter('plugin_action_links', 'ResursBank\Helper\WooCommerce::getPluginAdminUrl', 10, 2);
        // Other calls.
        add_filter('rbwc_admin_dynamic_content', 'ResursBank\Gateway\AdminPage::getAdminDynamicContent', 10, 2);
        // Data calls.
        add_filter('rbwc_get_plugin_information', 'ResursBank\Module\Data::getPluginInformation');
        // Localization.
        add_filter('rbwc_localizations_generic', 'ResursBank\Helper\WooCommerce::getGenericLocalization', 10, 2);
        // Helper calls.
        add_filter('woocommerce_get_settings_pages', 'ResursBank\Helper\WooCommerce::getSettingsPages');
        add_filter('woocommerce_payment_gateways', 'ResursBank\Helper\WooCommerce::getGateways');
        add_filter('is_protected_meta', 'ResursBank\Helper\WooCommerce::getProtectedMetaData', 10, 3);
        add_filter('rbwc_register_ajax_actions', 'ResursBank\Helper\WordPress::registerAjaxActions');
    }

    /**
     * Script preparation.
     *
     * @since 0.0.1.0
     */
    private static function setupScripts()
    {
        add_action('wp_enqueue_scripts', 'ResursBank\Helper\WordPress::setResursBankScripts');
        add_action('admin_enqueue_scripts', 'ResursBank\Helper\WordPress::setResursBankScriptsAdmin');
    }

    /**
     * Basic actions.
     *
     * @since 0.0.1.0
     */
    private static function setupActions()
    {
        $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
        add_action('admin_notices', 'ResursBank\Helper\WordPress::getAdminNotices');
        add_action('rbwc_get_localized_scripts', 'ResursBank\Helper\WordPress::getLocalizedScripts', 10, 3);
        add_action('rbwc_localizations_admin', 'ResursBank\Helper\WordPress::getLocalizedScriptsDeprecated', 10, 2);
        add_action('wp_ajax_' . $action, 'ResursBank\Module\PluginApi::execApi');
        add_action('wp_ajax_nopriv_' . $action, 'ResursBank\Module\PluginApi::execApiNoPriv');
        add_action('woocommerce_admin_field_button', 'ResursBank\Module\FormFields::getFieldButton', 10, 2);
        add_action('woocommerce_admin_field_decimal_warning', 'ResursBank\Module\FormFields::getFieldDecimals', 10, 2);
        add_action('woocommerce_admin_field_methodlist', 'ResursBank\Module\FormFields::getFieldMethodList', 10, 2);
        add_action('woocommerce_admin_field_callbacklist', 'ResursBank\Module\FormFields::getFieldCallbackList', 10, 2);
        add_filter('woocommerce_get_settings_general', 'ResursBank\Module\Data::getGeneralSettings');
    }

    /**
     * Admin events.
     *
     * @since 0.0.1.0
     */
    private static function setupWoocommerceAdminActions()
    {
        add_action(
            'woocommerce_admin_order_data_after_order_details',
            'ResursBank\Helper\WooCommerce::getAdminAfterOrderDetails'
        );
        add_action(
            'woocommerce_admin_order_data_after_billing_address',
            'ResursBank\Helper\WooCommerce::getAdminAfterBilling'
        );
        add_action(
            'woocommerce_admin_order_data_after_shipping_address',
            'ResursBank\Helper\WooCommerce::getAdminAfterShipping'
        );
    }

    /**
     * Customer based events (checkout, etc).
     *
     * @since 0.0.1.0
     */
    private static function setupWoocommerceCheckoutActions()
    {
        // v3core: Customer is in checkout.
        add_action(
            'woocommerce_before_checkout_form',
            'ResursBank\Helper\WooCommerce::getBeforeCheckoutForm'
        );
        // v3core: Customer is not in checkout.
        add_action(
            'woocommerce_add_to_cart',
            'ResursBank\Helper\WooCommerce::getAddToCart'
        );
        // v3core: Customer is not in checkout.
        add_action(
            'woocommerce_cart_updated',
            'ResursBank\Helper\WooCommerce::getAddToCart'
        );
        // v3core: Customer is not in checkout.
        add_action(
            'woocommerce_update_order_review_fragments',
            'ResursBank\Helper\WooCommerce::getReviewFragments'
        );
        add_action(
            'woocommerce_checkout_update_order_review',
            'ResursBank\Helper\WooCommerce::getOrderReviewSettings'
        );
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getAdminNotices()
    {
        global $current_tab, $parent_file;

        if (isset($_SESSION[Data::getPrefix()]['exception'])) {
            $class = 'notice notice-error is-dismissible';
            foreach ($_SESSION[Data::getPrefix()]['exception'] as $index => $item) {
                printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($item->getMessage()));
            }
            unset($_SESSION[Data::getPrefix()]['exception']);
        }

        $requiredVersionNotice = sprintf(
            __(
                'The current plugin "%s" requires at least version %s - for the moment, you are running ' .
                'on version %s. You should consider upgrading as soon as possible.',
                'trbwc'
            ),
            Data::getPluginTitle(true),
            WooCommerce::getRequiredVersion(),
            WooCommerce::getWooCommerceVersion()
        );

        try {
            if ($parent_file === 'woocommerce' || $current_tab === Data::getPrefix('admin')) {
                WooCommerce::testRequiredVersion(false);
            }
        } catch (Exception $e) {
            //Data::setLogInternal(Data::LOG_NOTICE, $requiredVersionNotice);
            Data::setLogException($e);
            echo Data::getGenericClass()->getTemplate(
                'adminpage_woocommerce_requirement',
                [
                    'requiredVersionNotice' => $requiredVersionNotice,
                ]
            );
        }
    }

    /**
     * @return bool
     * @since 0.0.1.0
     * @noinspection PhpExpressionResultUnusedInspection
     */
    public static function getPriorVersionsDisabled()
    {
        return Data::getResursOption('priorVersionsDisabled');
    }

    /**
     * @since 0.0.1.0
     */
    public static function setResursBankScriptsAdmin()
    {
        self::setResursBankScripts(true);
    }

    /**
     * @param bool $isAdmin
     * @since 0.0.1.0
     */
    public static function setResursBankScripts($isAdmin = null)
    {
        foreach (Data::getPluginStyles($isAdmin) as $styleName => $styleFile) {
            wp_enqueue_style(
                sprintf('%s_%s', Data::getPrefix(), $styleName),
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
            $realScriptName = sprintf('%s_%s', Data::getPrefix(), $scriptName);
            self::setEnqueue($realScriptName, $scriptFile, $isAdmin);
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
     * @param $filterName
     * @param $value
     * @return mixed|void
     * @since 0.0.1.0
     * @deprecated Marked deprecated, use the new definitions instead.
     */
    public static function applyFiltersDeprecated($filterName, $value)
    {
        $return = apply_filters(
            sprintf('%s_%s', 'resurs_bank', self::getFilterName($filterName)),
            $value,
            self::getFilterArgs(func_get_args())
        );

        // This dual filter solutions isn't very clever.
        if ($return === null) {
            $return = apply_filters(
                sprintf('%s_%s', 'resursbank', self::getFilterName($filterName)),
                $value,
                self::getFilterArgs(func_get_args())
            );
        }

        return $return;
    }

    /**
     * @param $scriptName
     * @param null $isAdmin
     * @param null $extraLocalizationData
     * @since 0.0.1.0
     */
    public static function getLocalizedScripts($scriptName, $isAdmin = null, $extraLocalizationData = null)
    {
        if (($localizationData = self::getLocalizationData($scriptName, (bool)$isAdmin))) {
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
     * @since 0.0.1.0
     */
    private static function getLocalizationData($scriptName, $isAdmin)
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
     * @since 0.0.1.0
     */
    private static function getLocalizationDataAdmin($return)
    {
        $return['noncify'] = self::getNonce('admin');
        $return['environment'] = Api::getEnvironment();
        $return['wsdl'] = Api::getWsdlMode();
        $return['translate_checkout_rco'] = __(
            'Resurs Checkout (RCO) is a one page stand-alone checkout, embedded as an iframe on the checkout ' .
            'page. It is intended to give you a full scale payment solution with all payment methods collected ' .
            'at the endpoint of Resurs Bank.',
            'trbwc'
        );
        $return['translate_checkout_simplified'] = __(
            'The integrated checkout (also known as the "simplified shop flow") is a direct integration with ' .
            'WooCommerce which uses intended APIs to interact with your customers while finishing the orders.',
            'trbwc'
        );
        $return['translate_checkout_hosted'] = __(
            '"Resurs Hosted Checkout" works similarly as the integrated simplified checkout, but on the ' .
            'checkout itself the customer are redirected to a hosted website to fulfill their payments. ' .
            'It can be quite easily compared with a Paypal solution.',
            'trbwc'
        );
        $return['resurs_test_credentials'] = __(
            'Validate and save credentials and payment methods',
            'trbwc'
        );
        $return['credential_failure_notice'] = __(
            'The credential check failed. If you save the current data we can not guarantee ' .
            'that your store will properly work.',
            'trbwc'
        );
        $return['credential_success_notice'] = __(
            'The credential check was successful. You may now save the rest of your data.',
            'trbwc'
        );
        $return['requireFraudControl'] = __(
            'This setting requires you to enable the fraud control.',
            'trbwc'
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
    private static function getNonce($tag, $strictify = true)
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
    public static function getNonceTag($tag, $strictify = true)
    {
        return Data::getPrefix($tag) . '|' . ($strictify ? $_SERVER['REMOTE_ADDR'] : '');
    }

    /**
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
     * @param $filterName
     * @return string
     * @since 0.0.1.0
     */
    private static function getFilterName($filterName)
    {
        $return = $filterName;
        if (defined('RESURSBANK_SNAKECASE_FILTERS')) {
            $return = (new Strings())->getSnakeCase($filterName);
        }

        return $return;
    }

    /**
     * Clean up arguments and return the real ones.
     *
     * @param $args
     * @return array
     * @since 0.0.1.0
     */
    private static function getFilterArgs($args)
    {
        if (is_array($args) && count($args) > 2) {
            array_shift($args);
            array_shift($args);
        }

        return $args;
    }

    /**
     * Localized variables shown in all views.
     *
     * @param $return
     * @return mixed
     * @since 0.0.1.0
     */
    private static function getLocalizationDataGlobal($return)
    {
        $return['noncify'] = self::getNonce('all');
        $return['ajaxify'] = admin_url('admin-ajax.php');
        $return['spin'] = Data::getImage('spin.gif');
        $return['success'] = __('Successful.', 'trbwc');
        $return['failed'] = __('Failed.', 'trbwc');
        $return['reloading'] = __('Please wait while reloading...', 'trbwc');
        $return['checkout_fields'] = FormFields::getFieldString();

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
        $importDeprecated = get_option('resursImportCredentials');

        if (!$importDeprecated) {
            $return['deprecated_login'] = !empty(Data::getResursOptionDeprecated('login')) ? true : false;
            $return['resurs_deprecated_credentials'] = __(
                'Import credentials from Resurs v2.x',
                'trbwc'
            );
            $return['credential_import_success'] = __(
                'Import successful.',
                'trbwc'
            );
            $return['credential_import_failed'] = __(
                'Import failed.',
                'trbwc'
            );
        }
        return $return;
    }
}
