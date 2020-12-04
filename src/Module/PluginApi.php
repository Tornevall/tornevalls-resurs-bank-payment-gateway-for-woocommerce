<?php

namespace ResursBank\Module;

use Exception;
use ResursBank\Helper\WooCommerce;
use ResursBank\Helper\WordPress;
use Resursbank\RBEcomPHP\RESURS_CALLBACK_TYPES;
use TorneLIB\Data\Password;
use TorneLIB\IO\Data\Strings;

/**
 * Class PluginApi
 *
 * @package ResursBank\Module
 */
class PluginApi
{
    /**
     * @since 0.0.1.0
     */
    public static function execApi()
    {
        WordPress::doAction(self::getAction(), null);
    }

    /**
     * @return string
     * @since 0.0.1.0
     */
    private static function getAction()
    {
        $action = isset($_REQUEST['action']) ? (string)$_REQUEST['action'] : '';

        return (new Strings())->getCamelCase(self::getTrimmedActionString($action));
    }

    /**
     * @param $action
     * @return mixed
     * @since 0.0.1.0
     */
    private static function getTrimmedActionString($action)
    {
        $action = ltrim($action, 'resursbank_');

        return $action;
    }

    /**
     * @since 0.0.1.0
     */
    public static function execApiNoPriv()
    {
        WordPress::doAction(self::getAction(), null);
    }

    /**
     * @since 0.0.1.0
     */
    public static function importCredentials()
    {
        update_option('resursImportCredentials', time());
        self::getValidatedNonce();
        self::reply([
            'login' => Data::getResursOptionDeprecated('login'),
            'pass' => Data::getResursOptionDeprecated('password'),
        ]);
    }

    /**
     * @param null $expire
     * @return bool
     * @since 0.0.1.0
     */
    private static function getValidatedNonce($expire = null)
    {
        $return = false;
        $expired = false;
        $defaultNonceError = 'nonce_validation';

        $nonceArray = [
            'admin',
            'all',
            'simple',
        ];

        // Not recommended as this expires immediately and stays expired.
        if ((bool)$expire && ($expired = self::expireNonce(__FUNCTION__))) {
            $defaultNonceError = 'nonce_expire';
        }

        foreach ($nonceArray as $nonceType) {
            if (wp_verify_nonce(self::getParam('n'), WordPress::getNonceTag($nonceType))) {
                $return = true;
                break;
            }
        }

        if (!$return || $expired) {
            self::reply(
                [
                    'error' => $defaultNonceError,
                ]
            );
        }

        return $return;
    }

    /**
     * Make sure the used nonce can only be used once.
     *
     * @param $nonceTag
     * @return bool
     * @since 0.0.1.0
     */
    public static function expireNonce($nonceTag)
    {
        $optionTag = 'resurs_nonce_' . $nonceTag;
        $return = false;
        $lastNonce = get_option($optionTag);
        if (self::getParam('n') === $lastNonce) {
            $return = true;
        } else {
            // Only update if different.
            update_option($optionTag, self::getParam('n'));
        }
        return $return;
    }

    /**
     * @param $key
     * @return mixed|string
     * @since 0.0.1.0
     */
    private static function getParam($key)
    {
        return isset($_REQUEST[$key]) ? $_REQUEST[$key] : '';
    }

    /**
     * @param $out
     * @since 0.0.1.0
     */
    private static function reply($out = null)
    {
        $success = true;

        if (!isset($out['error'])) {
            $out['error'] = null;
        }
        if (!isset($out['ajax_success'])) {
            if (!empty($out['error'])) {
                $success = false;
            }
            $out['ajax_success'] = $success;
        }

        header('Content-type: application/json; charset=utf-8', true, 200);
        echo json_encode($out);
        die();
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function testCredentials()
    {
        self::getValidatedNonce();

        $validationResponse = (new Api())->getConnection()->validateCredentials(
            (self::getParam('e') !== 'live'),
            self::getParam('u'),
            self::getParam('p')
        );

        // Save when validating.
        if ($validationResponse) {
            Data::setResursOption('login', self::getParam('u'));
            Data::setResursOption('password', self::getParam('p'));
            Data::setResursOption('environment', self::getParam('e'));
            Api::getPaymentMethods(false);
            Api::getAnnuityFactors(false);
        }

        Data::setLogNotice(
            sprintf(
                __(
                    'Credentials for Resurs was validated before saving. Response was %s.',
                    'trbwc'
                ),
                $validationResponse
            )
        );

        self::reply([
            'validation' => $validationResponse,
        ]);
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getPaymentMethods()
    {
        // Re-fetch payment methods.
        Api::getPaymentMethods(false);
        Api::getAnnuityFactors(false);
        self::reply([
            'reload' => true,
        ]);
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getNewCallbacks()
    {
        $callbacks = [
            RESURS_CALLBACK_TYPES::UNFREEZE,
            RESURS_CALLBACK_TYPES::ANNULMENT,
            RESURS_CALLBACK_TYPES::AUTOMATIC_FRAUD_CONTROL,
            RESURS_CALLBACK_TYPES::FINALIZATION,
            RESURS_CALLBACK_TYPES::TEST,
            RESURS_CALLBACK_TYPES::UPDATE,
            RESURS_CALLBACK_TYPES::BOOKED,
        ];

        foreach ($callbacks as $ecomCallbackId) {
            Api::getResurs()->setRegisterCallback(
                $ecomCallbackId,
                self::getCallbackUrl(self::getCallbackParams($ecomCallbackId)),
                self::getCallbackDigestData()
            );

        }

        self::reply(['reload' => true]);
    }

    /**
     * @param $callbackParams
     * @return string
     * @since 0.0.1.0
     */
    private static function getCallbackUrl($callbackParams)
    {
        $return = WooCommerce::getWcApiUrl();

        if (is_array($callbackParams)) {
            foreach ($callbackParams as $cbKey => $cbValue) {
                $return = add_query_arg($cbKey, $cbValue, $return);
            }
        }
        return $return;
    }

    /**
     * @param $ecomCallbackId
     * @return array
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function getCallbackParams($ecomCallbackId)
    {
        $params = [
            'c' => Api::getResurs()->getCallbackTypeString($ecomCallbackId),
            't' => time(),
        ];
        switch ($ecomCallbackId) {
            case RESURS_CALLBACK_TYPES::AUTOMATIC_FRAUD_CONTROL:
                $params += [
                    'p' => '{paymentId}',
                    'd' => '{digest}',
                    'result' => 'ignored',
                ];
                break;
            case RESURS_CALLBACK_TYPES::TEST:
                $params += [
                    'ignore1' => '{param1}',
                    'ignore2' => '{param2}',
                    'ignore3' => '{param3}',
                    'ignore4' => '{param4}',
                    'ignore5' => '{param5}',
                ];
                unset($params['t']);
                break;
            default:
                // UNFREEZE, ANNULMENT, FINALIZATION, UPDATE, BOOKED
                $params['p'] = '{paymentId}';
                $params['d'] = '{digest}';
        }

        return $params;
    }

    /**
     * @return array
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function getCallbackDigestData()
    {
        $return = [
            'digestSalt' => self::getTheSalt(),
        ];

        return $return;
    }

    /**
     * @return string
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function getTheSalt()
    {
        $currentSaltData = (int)Data::getResursOption('saltdate');
        $saltDateControl = time() - $currentSaltData;
        $currentSalt = ($return = Data::getResursOption('salt'));

        if (empty($currentSalt) || $saltDateControl >= 86400) {
            $return = (new Password())->mkpass();
            Data::setResursOption('salt', $return);
            Data::setResursOption('saltdate', time());
        }

        return $return;
    }

    /**
     * @return bool
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getTriggerTest()
    {
        Api::getResurs()->triggerCallback();
        return true;
    }
}
