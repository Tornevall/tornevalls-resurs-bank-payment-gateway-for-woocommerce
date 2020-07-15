<?php

namespace ResursBank\Helper;

use Exception;
use ResursBank\Module\Api;
use ResursBank\Module\Data;
use TorneLIB\IO\Data\Strings;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WordPress WordPress related actions.
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
        self::setupActions();
        self::setupScripts();
    }

    /**
     * @since 0.0.1.0
     */
    private static function setupAjaxActions()
    {
        $actionList = [
            'test_credentials',
            'import_credentials',
        ];
        foreach ($actionList as $action) {
            $camelCaseAction = sprintf('ResursBank\Module\PluginApi::%s', Strings::returnCamelCase($action));
            add_action(
                sprintf('rbwc_%s', $action),
                $camelCaseAction
            );
            //if (method_exists('ResursBank\Module\PluginApi', Strings::returnCamelCase($action))) {}
        }
    }

    /**
     * Internal filter setup.
     * @since 0.0.1.0
     */
    private static function setupFilters()
    {
        // Generic calls.
        add_filter('plugin_action_links', 'ResursBank\Helper\WooCommerce::getPluginAdminUrl', 10, 2);
        // Helper calls.
        add_filter('woocommerce_get_settings_pages', 'ResursBank\Helper\WooCommerce::getSettingsPages');
        add_filter('woocommerce_payment_gateways', 'ResursBank\Helper\WooCommerce::getGateway');
        add_action('rbwc_get_localized_scripts', 'ResursBank\Helper\WordPress::getLocalizedScripts', 10, 2);
        add_action('rbwc_localizations_admin', 'ResursBank\Helper\WordPress::getLocalizedScriptsDeprecated', 10, 2);
        // Data calls.
        add_filter('rbwc_get_plugin_information', 'ResursBank\Module\Data::getPluginInformation');
        // Other calls.
        add_filter('rbwc_admin_dynamic_content', 'ResursBank\Gateway\AdminPage::getAdminDynamicContent', 10, 2);
    }

    /**
     * @since 0.0.1.0
     */
    private static function setupActions()
    {
        $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
        //add_action('rbwc_event_logger', 'ResursBank\Module\Data::setLogInternal', 10, 2);
        add_action('admin_notices', 'ResursBank\Helper\WordPress::getAdminNotices');
        add_action('wp_ajax_' . $action, 'ResursBank\Module\PluginApi::execApi');
        add_action('wp_ajax_nopriv_' . $action, 'ResursBank\Module\PluginApi::execApiNoPriv');
    }

    /**
     * @since 0.0.1.0
     */
    private static function setupScripts()
    {
        add_action('wp_enqueue_scripts', 'ResursBank\Helper\WordPress::setResursBankScripts');
        add_action('admin_enqueue_scripts', 'ResursBank\Helper\WordPress::setResursBankScriptsAdmin');
    }

    /**
     * @since 0.0.1.0
     */
    public static function getAdminNotices()
    {
        global $current_tab, $parent_file;

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
            if ($current_tab === Data::getPrefix('admin') || $parent_file === 'woocommerce') {
                WooCommerce::testRequiredVersion(false);
            }
        } catch (Exception $e) {
            //self::doAction('eventLogger', Data::LOG_WARNING, $requiredVersionNotice);
            Data::setLogInternal(Data::LOG_NOTICE, $requiredVersionNotice);
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
            wp_enqueue_script(
                $realScriptName,
                sprintf(
                    '%s/js/%s?%s',
                    Data::getGatewayUrl(),
                    $scriptFile,
                    Data::getTestMode() ? Data::getPrefix() . '-' . time() : 'static'
                ),
                Data::getJsDependencies($scriptName, $isAdmin)
            );
            WordPress::doAction('getLocalizedScripts', $realScriptName, $isAdmin);
        }
    }

    /**
     * @param $actionName
     * @param $value
     * @return mixed
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
     * @param $filterName
     * @param $value
     * @return mixed|void
     * @since 0.0.1.0
     * @deprecated Marked deprecated, use the new definitions instead.
     */
    public static function applyFiltersDeprecated($filterName, $value)
    {
        return apply_filters(
            sprintf('%s_%s', 'resurs_bank', self::getFilterName($filterName)),
            $value,
            self::getFilterArgs(func_get_args())
        );
    }

    /**
     * @param $scriptName
     * @param bool $isAdmin
     * @since 0.0.1.0
     */
    public static function getLocalizedScripts($scriptName, $isAdmin = false)
    {
        if (($localizationData = self::getLocalizationData($scriptName, $isAdmin))) {
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
            $return = self::getLocalizationDataFront($return);
        }

        return $return;
    }

    /**
     * Localized variables shown in admin only.
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
            'Validate credentials',
            'trbwc'
        );
        $return['credential_failure_notice'] = __(
            'The credential check failed. If you save the current data we can not guarantee ' .
            'that your store will properly work.',
            'trbwc'
        );
        $return['credential_success_notice'] = __(
            'The credential check was successful. You may now save the data.',
            'trbwc'
        );

        return self::applyFilters('localizationsAdmin', $return);
    }

    /**
     * Makes nonces strict based on client ip address.
     * @param $tag
     * @param bool $strictify
     * @return string
     * @since 0.0.1.0
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
     * Localized variables shown in all views.
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

        return self::applyFilters('localizationsGlobal', $return);
    }

    /**
     * Localized variables shown in front (customer) view only.
     * @param $return
     * @return mixed
     * @since 0.0.1.0
     */
    private static function getLocalizationDataFront($return)
    {
        $return['noncify'] = self::getNonce('simple');

        return self::applyFilters('localizationsFront', $return);
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
                'Importen lyckades.',
                'trbwc'
            );
            $return['credential_import_failed'] = __(
                'Importen misslyckades.',
                'trbwc'
            );
        }
        return $return;
    }
}
