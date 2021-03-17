<?php

namespace ResursBank\Module;

use Exception;
use ResursBank\Helpers\WooCommerce;
use ResursBank\Helpers\WordPress;
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
        $returnedValue = WordPress::applyFilters(self::getAction(), null, $_REQUEST);
        if (!empty($returnedValue)) {
            self::reply($returnedValue);
        }
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
     * @param null $noReply Boolean that returns the answer instead of replying live.
     * @return bool
     * @since 0.0.1.0
     */
    private static function getValidatedNonce($expire = null, $noReply = null)
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
            if (!(bool)$noReply) {
                self::reply(
                    [
                        'error' => $defaultNonceError,
                    ]
                );
            }
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
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function testCredentials()
    {
        $isValid = self::getValidatedNonce(null, true);
        $validationResponse = false;

        if ($isValid) {
            $validationResponse = (new Api())->getConnection()->validateCredentials(
                (self::getParam('e') !== 'live'),
                self::getParam('u'),
                self::getParam('p')
            );
        }

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
            $callbackUrl = self::getCallbackUrl(self::getCallbackParams($ecomCallbackId));
            try {
                Data::setLogNotice(sprintf('Callback Registration: %s.', $callbackUrl));
                Api::getResurs()->setRegisterCallback(
                    $ecomCallbackId,
                    $callbackUrl,
                    self::getCallbackDigestData()
                );
            } catch (Exception $e) {
                Data::setLogException($e);
            }
        }

        return ['reload' => true];
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
                // Selective carefulness.
                if (preg_match('/{|}/', $cbValue)) {
                    // Variables specifically sent to Resurs Bank that need to be left as is.
                    $return = rawurldecode(add_query_arg($cbKey, $cbValue, $return));
                } else {
                    $return = add_query_arg($cbKey, $cbValue, $return);
                }
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
                    'r' => '{result}',
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
     * Trigger TEST callback at Resurs Bank.
     *
     * @return bool
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getTriggerTest()
    {
        Data::setResursOption('resurs_callback_test_response', null);
        $return = WordPress::applyFiltersDeprecated('resurs_trigger_test_callback', null);
        $return['api'] = (bool)Api::getResurs()->triggerCallback();
        $return['html'] = sprintf(
            '<div>%s</div><div id="resursWaitingForTest"></div>',
            sprintf(
                __('Activated test trigger. Response "%s" received.', 'trbwc'),
                $return['api'] ? 'success' : 'fail'
            )
        );
        $return = WordPress::applyFilters('triggerCallback', $return);
        return $return;
    }

    /**
     * @return array
     * @since 0.0.1.0
     */
    public static function getTriggerResponse()
    {
        $runTime = 0;
        $success = false;
        if (isset($_REQUEST['runTime'])) {
            $runTime = (int)$_REQUEST['runTime'];
        }
        if ((int)Data::getResursOption('resurs_callback_test_response') > 0) {
            $lastResponse = sprintf(
                '%s %s',
                __('Received', 'trbwc'),
                strftime('%Y-%m-%d %H:%M:%S', Data::getResursOption('resurs_callback_test_response'))
            );
            $success = true;
        } else {
            $lastResponse =
                sprintf(
                    __(
                        'Waiting for callback TEST (%d seconds).'
                    ),
                    $runTime
                );
        }
        $return = [
            'lastResponse' => $lastResponse,
            'runTime' => $runTime,
            'success' => $success,
        ];

        return $return;
    }

    /**
     * Log an event of getAddress.
     *
     * @param $customerCountry
     * @param $identification
     * @param $runFunctionInfo
     * @since 0.0.1.0
     */
    private static function getAddressLog($customerCountry, $customerType, $identification, $runFunctionInfo)
    {
        Data::canLog(
            Data::CAN_LOG_ORDER_EVENTS,
            sprintf(
                __('getAddress request (country %s, type %s) for %s: %s', 'trbwc'),
                $customerCountry,
                $customerType,
                $identification,
                $runFunctionInfo
            )
        );
    }

    /**
     * @return array
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getAddress()
    {
        $apiRequest = Api::getResurs();
        $addressResponse = [];
        $identification = WooCommerce::getRequest('identification');
        $customerType = Data::getCustomerType();
        $customerCountry = Data::getCustomerCountry();

        $return = [
            'api_error' => '',
            'code' => 0,
            'identificationResponse' => [],
        ];

        try {
            WooCommerce::setSessionValue('identification', WooCommerce::getRequest('$identification'));

            switch ($customerCountry) {
                case 'NO':
                    // This request works only on norwegian accounts.
                    try {
                        $addressResponse = (array)$apiRequest->getAddressByPhone($identification, $customerType);
                        self::getAddressLog($customerCountry, $customerType, $identification, __(
                            'By phone request.',
                            'trbwc'
                        ));
                    } catch (Exception $e) {
                        // If we get an error here, it might be cause by credential errors.
                        // In that case lets fall back to the default lookup.
                        $addressResponse = (array)$apiRequest->getAddress($identification, $customerType);
                        self::getAddressLog($customerCountry, $customerType, $identification, __(
                            'By phone request failed, executed failover by government id.',
                            'trbwc'
                        ));
                    }
                    break;
                case 'SE':
                    try {
                        $addressResponse = (array)$apiRequest->getAddress($identification, $customerType);
                        self::getAddressLog($customerCountry, $customerType, $identification, __(
                            'By government id/company id (See customer type).',
                            'trbwc'
                        ));
                    } catch (Exception $e) {
                        self::getAddressLog(
                            $customerCountry,
                            $customerType,
                            $identification,
                            sprintf(
                                __(
                                    'By government id/company id (See customer type), but failed: (%d) %s.',
                                    'trbwc'
                                ),
                                $e->getCode(),
                                $e->getMessage()
                            )
                        );
                    }
                    break;
                default:
            }
            $return['identificationResponse'] = $addressResponse;
        } catch (Exception $e) {
            $return['api_error'] = $e->getMessage();
            $return['code'] = $e->getCode();
        }

        if (is_array($addressResponse) && count($addressResponse)) {
            $return = self::getTransformedAddressResponse($return, $addressResponse);
        }

        self::reply($return);
    }

    /**
     * Transform getAddress responses into WooCommerce friendly data fields so that they
     * can be easily pushed out to the default forms.
     * @param $return
     * @param $addressResponse
     * @return mixed
     * @since 0.0.1.0
     */
    private static function getTransformedAddressResponse($return, $addressResponse)
    {
        $compileKeys = [];
        $compileInfo = [];
        $addressFields = WordPress::applyFilters('getAddressFieldController', []);
        foreach ($addressFields as $addressField => $addressTransform) {
            // Check if the session is currently holding something that we want to put up in some fields.
            $wooSessionData = trim(WooCommerce::getRequest($addressTransform));
            if (!empty($wooSessionData)) {
                $addressResponse[$addressTransform] = $wooSessionData;
            }
            if (!preg_match('/:/', $addressTransform)) {
                $return[$addressField] = isset($addressResponse[$addressTransform]) ?
                    $addressResponse[$addressTransform] : '';
            } else {
                $splitInfo = explode(':', $addressTransform);
                foreach ($splitInfo as $splitKey) {
                    $compileInfo[] = '%s';
                    $compileKeys[] = isset($addressResponse[$splitKey]) ? $addressResponse[$splitKey] : '';
                }
                $return[$addressField] = vsprintf(implode(' ', $compileInfo), $compileKeys);
            }
        }

        return $return;
    }

}
