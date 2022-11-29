<?php

/** @noinspection CompactCanBeUsedInspection */

/** @noinspection ParameterDefaultValueIsNotNullInspection */

namespace ResursBank\Module;

use Exception;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Module\Customer\Enum\CustomerType;
use Resursbank\Ecom\Module\Customer\Repository as CustomerRepoitory;
use Resursbank\Ecommerce\Types\Callback;
use ResursBank\Gateway\ResursCheckout;
use ResursBank\Gateway\ResursDefault;
use ResursBank\Service\WooCommerce;
use ResursBank\Service\WordPress;
use Resursbank\Woocommerce\Database\Options\StoreId;
use ResursException;
use RuntimeException;
use TorneLIB\Data\Password;
use TorneLIB\Exception\ExceptionHandler;
use TorneLIB\IO\Data\Strings;
use TorneLIB\Module\Network\Domain;
use TorneLIB\Module\Network\NetWrapper;
use TorneLIB\Module\Network\Wrappers\CurlWrapper;
use WC_Checkout;
use WC_Order;

use function count;
use function in_array;
use function is_array;
use function is_bool;

/**
 * Backend API Handler.
 *
 * @package ResursBank\Module
 */
class PluginApi
{
    /**
     * @var ResursDefault
     * @since 0.0.1.0
     */
    private static $resursCheckout;

    /**
     * List of callbacks required for this plugin to handle payments properly.
     * @var array
     * @since 0.0.1.0
     */
    private static $callbacks = [
        Callback::UNFREEZE,
        Callback::TEST,
        Callback::UPDATE,
        Callback::BOOKED,
    ];

    /**
     * @since 0.0.1.0
     */
    public static function execApi()
    {
        // Making sure ecom2 is preconfigured during ajax-calls too.
        new ResursBankAPI();
        // Logging $_REQUEST may break the WooCommerce status log view if not decoded first.
        // For some reason, the logs can't handle utf8-strings.
        Data::writeLogEvent(
            Data::CAN_LOG_BACKEND,
            sprintf(
                'Backend: %s (%s), params %s',
                __FUNCTION__,
                self::getActionFromRequest(),
                print_r(Data::getObfuscatedData($_REQUEST), true)
            )
        );

        $returnedValue = WordPress::applyFilters(self::getActionFromRequest(), null, $_REQUEST);

        if (!empty($returnedValue)) {
            self::reply($returnedValue);
        }
    }

    /**
     * @since 0.0.1.0
     */
    public static function execApiNoPriv()
    {
        // Making sure ecom2 is preconfigured during ajax-calls too.
        new ResursBankAPI();
        WordPress::doAction(self::getActionFromRequest(), null);
    }

    /**
     * @return string
     * @throws Exception
     */
    private static function getActionFromRequest(): string
    {
        return WordPress::getCamelCase(self::getTrimmedActionString(Data::getRequest('action')));
    }

    /**
     * @param $action
     * @return mixed
     * @since 0.0.1.0
     */
    private static function getTrimmedActionString($action)
    {
        $action = preg_replace('/^resursbank_/i', '', $action);

        return $action;
    }

    /**
     * @param null $out
     * @param bool $dieInstantly Set to exit after reply if true.
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function reply($out = null, $dieInstantly = true)
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

        $out['action'] = self::getActionFromRequest();

        Data::writeLogEvent(
            Data::CAN_LOG_BACKEND,
            sprintf(
                'Backend Reply: %s (%s), params %s',
                __FUNCTION__,
                self::getActionFromRequest(),
                print_r(self::getActionFromRequest() !== 'getAddress' ? $out : Data::getObfuscatedData(
                    $out,
                    'identificationResponse'
                ), true)
            )
        );

        header('Content-type: application/json; charset=utf-8', true, 200);
        // Can not sanitize output as the browser is strictly typed to specific content.
        echo json_encode($out);
        if ($dieInstantly) {
            exit;
        }
    }

    /**
     * @since 0.0.1.0
     */
    public static function importCredentials($force = null)
    {
        Data::writeLogInfo(
            __('Import of old credentials initiated.', 'resurs-bank-payments-for-woocommerce')
        );
        if (!(bool)$force) {
            self::getValidatedNonce();
        }

        $imports = [
            'resursAnnuityDuration' => 'currentAnnuityDuration',
            'resursAnnuityMethod' => 'currentAnnuityFactor',
            'partPayWidgetPage' => 'part_payment_template',
            'login' => 'jwt_client_id',
            'password' => 'jwt_client_secret',
            'checkout_type' => 'checkout_type',
        ];

        foreach ($imports as $key => $destKey) {
            $oldValue = Data::getResursOptionDeprecated($key);

            // Below: Selectively choosing credentials based on the current environment.
            switch ($key) {
                case 'login':
                    if (Data::isTest()) {
                        Data::setResursOption($destKey, $oldValue);
                    } else {
                        Data::setResursOption('jwt_client_id_production', $oldValue);
                    }
                    break;
                case 'password':
                    if (Data::isTest()) {
                        Data::setResursOption($destKey, $oldValue);
                    } else {
                        Data::setResursOption('jwt_client_secret_production', $oldValue);
                    }
                    break;
                case 'postidreference':
                    // if postidreference is set to true, that matches with the 'postid' in the new version.
                    Data::setResursOption($destKey, (bool)$oldValue ? 'postid' : 'ecom');
                    break;
                default:
                    if (!empty($oldValue)) {
                        Data::setResursOption($destKey, $oldValue);
                    }
            }
        }

        Data::setResursOption('resursImportCredentials', time());
        Data::writeLogInfo(
            __('Import of old credentials finished.', 'resurs-bank-payments-for-woocommerce')
        );

        if (!$force) {
            self::reply([
                'login' => Data::getResursOptionDeprecated('login'),
                'pass' => Data::getResursOptionDeprecated('password'),
            ]);
        }
    }

    /**
     * @param null $expire
     * @param null $noReply Boolean that returns the answer instead of replying live.
     * @return bool
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getValidatedNonce(
        $expire = null,
        $noReply = null,
        $fromFunction = null
    ): bool {
        $return = false;
        $expired = false;
        $preExpired = self::expireNonce(__FUNCTION__);
        $defaultNonceError = 'nonce_validation';

        $isNotSafe = [
            'resetPluginSettings',
            'resetOldPluginSettings',
        ];

        $isSafe = (is_admin() && is_ajax() && empty($fromFunction) && in_array($fromFunction, $isNotSafe, true));
        $nonceArray = [
            'admin',
            'all',
            'simple',
        ];

        // Not recommended as this expires immediately and stays expired.
        if ((bool)$expire && $preExpired) {
            $expired = $preExpired;
            $defaultNonceError = 'nonce_expire';
        }

        foreach ($nonceArray as $nonceType) {
            if (wp_verify_nonce(Data::getRequest('n'), WordPress::getNonceTag($nonceType))) {
                $return = true;
                break;
            }
        }

        if (!$return && $isSafe && (bool)Data::getResursOption('nonce_trust_admin_session')) {
            // If request is based on ajax and admin.
            $return = true;
            $expired = false;
        }

        // We do not ask if this can be logged before it is logged, so that we can backtrack
        // errors without the permission from wp-admin.
        Data::writeLogInfo(
            sprintf(
                __(
                    'Nonce validation accepted (from function: %s): %s.',
                    'resurs-bank-payments-for-woocommerce'
                ),
                $fromFunction,
                $return ? 'true' : 'false'
            )
        );

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
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function expireNonce($nonceTag): bool
    {
        $optionTag = 'resurs_nonce_' . $nonceTag;
        $return = false;
        $lastNonce = get_option($optionTag);
        if (Data::getRequest('n') === $lastNonce) {
            $return = true;
        } else {
            // Only update if different.
            update_option($optionTag, Data::getRequest('n'));
        }
        return $return;
    }

    /**
     * Simple synchronizer for payment methods in RCOv2.
     *
     * @throws Exception
     * @since 0.0.1.5
     */
    public static function resursBankRcoSynchronize()
    {
        $rcoId = Data::getRequest('id');

        if (empty($rcoId)) {
            // Synchronize and store payment method information in an early state.
            WooCommerce::setSessionValue('paymentMethod', $rcoId);
        }

        self::reply(
            [
                'noAction' => true,
            ]
        );
    }

    /**
     * @throws Exception
     * @throws ResursException
     * @throws ExceptionHandler
     * @since 0.0.1.0
     */
    public static function getCostOfPurchase()
    {
        $method = Data::getRequest('method');
        $total = Data::getRequest('total');
        if (Data::getCustomerCountry() !== 'DK') {
            $priceInfoHtml = ResursBankAPI::getResurs()->getCostOfPriceInformation($method, $total, true, true);
        } else {
            $priceInfoHtml = ResursBankAPI::getResurs()->getCostOfPriceInformation(
                ResursBankAPI::getPaymentMethods(),
                $total,
                false,
                true
            );
        }
        $hasMock = WooCommerce::applyMock('emptyPriceInfoHtml');
        if ($hasMock !== null) {
            $priceInfoHtml = $hasMock;
        }

        if (empty($priceInfoHtml)) {
            $priceInfoHtml = __(
                'Price information request retrieved no content from Resurs Bank.',
                'resurs-bank-payments-for-woocommerce'
            );
        }

        // Echoing this without escaping data through an external template as wp_head() is not properly
        // supported by wp_kses (too many html tags to approve).
        echo '
            <html>
            <head>' . wp_head() . '</head>
            <body>' . Data::getEscapedHtml($priceInfoHtml) . '
            </body>
        </html>
        ';
        die;
    }

    /**
     * @param bool $reply
     * @param bool $validate
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection BadExceptionsProcessingInspection
     */
    public static function getPaymentMethods($reply = true, $validate = true)
    {
        if ($validate) {
            self::getValidatedNonce();
        }
        $e = null;

        // Re-fetch payment methods.
        try {
            ResursBankAPI::getPaymentMethods(false);
            // @todo Can't use old SOAP annuities, so we disable this one during the initial migration!
            //ResursBankAPI::getAnnuityFactors(false);
            $canReload = true;
        } catch (Exception $e) {
            Data::writeLogException($e, __FUNCTION__);
            $canReload = false;
        }
        if (self::canReply($reply)) {
            self::reply([
                'reload' => $canReload,
                'error' => $e instanceof Exception ? $e->getMessage() : '',
                'code' => $e instanceof Exception ? $e->getCode() : 0,
            ]);
        }
    }

    /**
     * @param $reply
     * @return bool
     * @since 0.0.1.0
     */
    private static function canReply($reply): bool
    {
        return $reply === null || (bool)$reply === true;
    }

    /**
     * Get delivery address by delivery address with fallback on billing.
     * This has also been used in the old plugin to match data correctly.
     *
     * @param $key
     * @param $deliveryArray
     * @param $billingArray
     * @return mixed|string
     * @since 0.0.1.0
     */
    private static function getDeliveryFrom($key, $deliveryArray, $billingArray)
    {
        if (isset($deliveryArray[$key]) && !empty($deliveryArray[$key])) {
            $return = $deliveryArray[$key];
        } elseif (isset($billingArray[$key]) && !empty($billingArray[$key])) {
            $return = $billingArray[$key];
        } else {
            $return = '';
        }

        return $return;
    }

    /**
     * @param $callbackParams
     * @return string
     * @since 0.0.1.0
     * @link https://docs.tornevall.net/display/TORNEVALL/Callback+URLs+explained
     * @todo We can re-use this to generate MAPI callbacks.
     */
    private static function getCallbackUrl($callbackParams): string
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
     * @link https://docs.tornevall.net/display/TORNEVALL/Callback+URLs+explained
     */
    private static function getCallbackParams($ecomCallbackId): array
    {
        $params = [
            'c' => ResursBankAPI::getResurs()->getCallbackTypeString($ecomCallbackId),
            't' => time(),
        ];

        if ($ecomCallbackId === Callback::TEST) {
            $params += [
                'ignore1' => '{param1}',
                'ignore2' => '{param2}',
                'ignore3' => '{param3}',
                'ignore4' => '{param4}',
                'ignore5' => '{param5}',
            ];
            unset($params['t']);
        } else {
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
    private static function getCallbackDigestData(): array
    {
        return [
            'digestSalt' => self::getTheSalt(),
        ];
    }

    /**
     * @return string
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function getTheSalt(): string
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
     * @since 0.0.1.0
     */
    public static function setNewAnnuity()
    {
        if (is_admin()) {
            $mode = Data::getRequest('mode');

            switch ($mode) {
                case 'e':
                    Data::setResursOption(
                        'currentAnnuityFactor',
                        Data::getRequest('id')
                    );
                    Data::setResursOption(
                        'currentAnnuityDuration',
                        (int)Data::getRequest('duration')
                    );
                    break;
                case 'd':
                    Data::delResursOption('currentAnnuityFactor');
                    Data::delResursOption('currentAnnuityDuration');
                    break;
                default:
            }
        }

        // Confirm Request.
        self::reply(
            [
                'id' => Data::getRequest('id'),
                'duration' => Data::getResursOption('currentAnnuityDuration'),
                'mode' => Data::getRequest('mode'),
            ]
        );
    }

    /**
     * @since 0.0.1.6
     */
    public static function setMethodState()
    {
        if (is_admin()) {
            $id = preg_replace('/^rbenabled_/', '', Data::getRequest('id'));
            $checked = Data::getTruth(Data::getRequest('checked'));

            $oldSetting = Data::getPaymentMethodSetting('enabled', $id);
            Data::setPaymentMethodSetting('enabled', $checked, $id);
            $newSetting = Data::getPaymentMethodSetting('enabled', $id);

            // This is vital information that should always be traceable.
            Data::writeLogInfo(
                sprintf(
                    __(
                        'Payment method %s has been %s.',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    $id,
                    $newSetting ? __('enabled', 'resurs-bank-payments-for-woocommerce') :
                        __('disabled', 'resurs-bank-payments-for-woocommerce')
                )
            );

            self::reply([
                'newState' => $oldSetting !== $newSetting,
                'id' => $id ?? null,
                'checked' => $checked ?? false,
            ]);
        } else {
            self::reply([
                'newState' => 'Not Allowed.',
            ]);
        }
    }

    /**
     * Network lookup handler, for whitelisting help at Resurs Bank.
     * @throws ExceptionHandler
     * @since 0.0.1.1
     */
    public static function getNetworkLookup()
    {
        if (is_admin()) {
            $addressRequestList = [];

            // Using curl_multi via netWrapper if exists, otherwise there's a backup to at multi-request stream.
            $httpWrapper = new NetWrapper();
            $serviceLookupErrors = false;
            $serviceException = null;
            try {
                $networkRequest = $httpWrapper->request($addressRequestUrls = [
                    'https://ipv4.netcurl.org',
                    'https://ipv6.netcurl.org',
                ]);
            } catch (Exception $e) {
                $serviceLookupErrors = true;
                $serviceException = $e;
                // Both responses dies when one error occurs here.
                $addressRequestList['4'] = 'service_error';
                $addressRequestList['6'] = 'service_error';
                // On exceptions from netcurl, we will instead use the extended version of the CurlWrapper but (as above)
                // prepare the address request list with service error information.
                $networkRequest = $e->getExtendException();
            }
            $noSoapRequestResponse = '';
            try {
                $noSoapRequest = new CurlWrapper();
                $soapRequestBody = $noSoapRequest->request(
                    'https://test.resurs.com/ecommerce-test/ws/V4/ConfigurationService?wsdl'
                )->getBody();
                if (preg_match('/<?xml/i', $soapRequestBody)) {
                    $noSoapRequestResponse = __(
                        'Resurs Bank SOAP-services is currently returning a valid XML-response.',
                        'resurs-bank-payments-for-woocommerce'
                    );
                }
            } catch (Exception $e) {
                $noSoapRequestResponse = sprintf(
                    'SoapRequest to Resurs Bank contained error: %s (%s)',
                    $e->getMessage(),
                    $e->getCode()
                );
            }

            $addressRequestList['Resurs XML Test'] = $noSoapRequestResponse;

            foreach ($addressRequestUrls as $addressRequestUrl) {
                if ($networkRequest instanceof CurlWrapper) {
                    $addressRequestResponse = $networkRequest->getParsed($addressRequestUrl);
                    $protoNum = self::getProtocolByHostName($addressRequestUrl);
                    if ($protoNum) {
                        if (isset($addressRequestResponse->ip) &&
                            filter_var($addressRequestResponse->ip, FILTER_VALIDATE_IP)
                        ) {
                            $addressRequestList[$protoNum] = $addressRequestResponse->ip;
                        } else {
                            $addressRequestList[$protoNum] = 'N/A';
                        }
                    }
                }
            }
        } else {
            $addressRequestList['4'] = '!is_admin()';
            $addressRequestList['6'] = '!is_admin()';
        }

        self::reply(
            [
                'addressRequest' => $addressRequestList,
            ]
        );
        die;
    }

    /**
     * @param $addressRequestUrl
     * @return int
     * @throws ExceptionHandler
     * @since 0.0.1.1
     */
    private static function getProtocolByHostName($addressRequestUrl): int
    {
        $return = 0;
        $domain = new Domain();
        $hostNameExtracted = $domain->getUrlDomain($addressRequestUrl);
        if (is_array($hostNameExtracted) && count($hostNameExtracted) === 3) {
            /** @var array $hostData */
            $hostData = explode('.', $hostNameExtracted[0]);
            if (is_array($hostData) && count($hostData) === 3 && preg_match('/^ipv\d/', $hostData[0])) {
                $return = (int)preg_replace('/[^0-9$]/i', '', $hostData[0]);
            }
        }

        return $return;
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getNewAnnuityCalculation()
    {
        $currencyRequest = empty(Data::getResursOption('part_payment_template')) ? [] : ['currency' => ' '];

        self::reply(
            [
                'price' => wc_price(
                    ResursBankAPI::getResurs()->getAnnuityPriceByDuration(
                        Data::getRequest('price'),
                        Data::getResursOption('currentAnnuityFactor'),
                        (int)Data::getResursOption('currentAnnuityDuration')
                    ),
                    $currencyRequest
                ),
            ]
        );
    }

    /**
     * Backend request to check if callbacks is matching our expecations or if they need to get updated.
     *
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getCallbackMatches()
    {
        $resursApiTest = new ResursBankAPI();
        $return['requireRefresh'] = false;
        $return['similarity'] = 0;

        if ($resursApiTest->getCredentialsPresent()) {
            $callbackConstant = 0;
            $errorMessage = '';

            foreach (self::$callbacks as $callback) {
                $callbackConstant += $callback;
            }

            $current_tab = Data::getRequest('t');
            $hasErrors = false;
            $freshCallbackList = [];
            $e = null;

            $resursApi = ResursBankAPI::getResurs();

            try {
                $freshCallbackList = $resursApi->getRegisteredEventCallback($callbackConstant);
                Data::clearCredentialNotice();
            } catch (Exception $e) {
                $hasErrors = true;

                $errorMessage = $e->getMessage();
                if ($e->getCode() === 401) {
                    Data::getCredentialNotice();
                } else {
                    if (Data::getTimeoutStatus() > 0) {
                        $errorMessage .= ' ' .
                            __(
                                'Connectivity may be a bit slower than normal.',
                                'resurs-bank-payments-for-woocommerce'
                            );
                    }

                    Data::setResursOption(
                        'front_callbacks_credential_error',
                        json_encode(['code' => $e->getCode(), 'message' => $errorMessage, 'function' => __FUNCTION__])
                    );
                }
            }

            if ($current_tab === sprintf('%s_admin', Data::getPrefix()) &&
                (time() - (int)Data::getResursOption('lastCallbackCheck')) > 60
            ) {
                $storedCallbacks = Data::getResursOption('callbacks');

                if (!$hasErrors) {
                    if (empty($storedCallbacks)) {
                        $return['requireRefresh'] = true;
                    } else {
                        foreach (self::$callbacks as $callback) {
                            $expectedUrl = self::getCallbackUrl(self::getCallbackParams($callback));
                            $callbackString = ResursBankAPI::getResurs()->getCallbackTypeString($callback);
                            if (isset($callbackString, $freshCallbackList[$callbackString])) {
                                similar_text(
                                    $expectedUrl,
                                    $freshCallbackList[$callbackString],
                                    $percentualValue
                                );
                            } else {
                                $percentualValue = 0;
                            }

                            if ($percentualValue < 90) {
                                $return['requireRefresh'] = true;
                            }
                            $return['similarity'] = $percentualValue;
                        }
                    }
                }
            }

            if (!$hasErrors && count($freshCallbackList) !== 4) {
                $return['requireRefresh'] = true;
            }
        }

        $return['errors'] = [
            'code' => isset($e) ? $e->getCode() : 0,
            'message' => isset($e) ? $errorMessage : null,
        ];

        self::reply($return);
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getInternalResynch()
    {
        $return = [
            'reload' => false,
            'errorstring' => '',
            'errorcode' => 0,
        ];
        if (is_admin()) {
            try {
                self::getNewCallbacks(false);
                ResursBankAPI::getPaymentMethods(false);
                ResursBankAPI::getAnnuityFactors(false);
            } catch (Exception $e) {
                $return['errorstring'] = $e->getMessage();
                $return['errorcode'] = $e->getCode();
            }
        } else {
            $return['errorstring'] = 'Not admin';
            $return['errorcode'] = 401;
        }

        self::reply(
            $return
        );
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function callbackUnregister()
    {
        $successRemoval = false;
        $callback = Data::getRequest('callback');
        $message = '';
        if ((bool)Data::getResursOption('show_developer')) {
            $successRemoval = ResursBankAPI::getResurs()->unregisterEventCallback(
                ResursBankAPI::getResurs()->getCallbackTypeByString($callback)
            );
        } else {
            $message = __(
                'Advanced mode is disabled. You can not make this change.',
                'resurs-bank-payments-for-woocommerce'
            );
        }

        self::reply(
            [
                'unreg' => $successRemoval,
                'callback' => $callback,
                'message' => $message,
            ]
        );
    }

    /**
     * Monitor credential changes.
     * @param $option
     * @param $old
     * @param $new
     * @since 0.0.1.0
     */
    public static function getOptionsControl($option, $old, $new)
    {
        $actOn = [
            sprintf('%s_admin_environment', Data::getPrefix()) => ['getNewCallbacks', 'getPaymentMethods'],
            sprintf('%s_admin_jwt_client_secret', Data::getPrefix()) => ['getPaymentMethods'],
            sprintf('%s_admin_jwt_client_secret_production', Data::getPrefix()) => ['getPaymentMethods'],
            sprintf('%s_admin_mapi_store_id', Data::getPrefix()) => ['getPaymentMethods'],
        ];
        if ($old !== $new && isset($actOn[$option]) && !is_ajax()) {
            foreach ($actOn[$option] as $execFunction) {
                try {
                    switch ($execFunction) {
                        case 'getPaymentMethods':
                            self::{$execFunction}(false, false);
                            break;
                        default:
                            self::{$execFunction}();
                    }
                    Data::clearCredentialNotice();
                } catch (Exception $e) {
                    if (is_admin() && $e->getCode() === 401) {
                        Data::getCredentialNotice();
                    }
                }
            }
        }
    }

    /**
     * Trigger TEST callback at Resurs Bank.
     *
     * @return array
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getTriggerTest(): array
    {
        Data::setResursOption('resurs_callback_test_start', time());
        Data::setResursOption('resurs_callback_test_response', 1);
        $return = WordPress::applyFiltersDeprecated('resurs_trigger_test_callback', null);
        $return['api'] = (bool)ResursBankAPI::getResurs()->triggerCallback();
        $return['html'] = sprintf(
            '<div>%s</div><div id="resursWaitingForTest"></div>',
            $return['api'] ? __(
                'Test activated.',
                'resurs-bank-payments-for-woocommerce'
            ) : __('Test failed to activate.', 'resurs-bank-payments-for-woocommerce')
        );
        $return = WordPress::applyFilters('triggerCallback', $return);
        return $return;
    }

    /**
     * @since 0.0.1.5
     * @noinspection OffsetOperationsInspection
     */
    public static function updatePaymentMethodDescription()
    {
        self::getValidatedNonce(false, false, __FUNCTION__);

        $id = explode('_', Data::getRequest('id'));
        $newValue = '';

        if (isset($id[2]) && !empty($id[2])) {
            $paymentMethod = Data::getPaymentMethodById($id[2]);
            if (is_object($paymentMethod)) {
                $storeAsKey = sprintf('method_custom_description_%s', $paymentMethod->id);
                Data::setResursOption($storeAsKey, sanitize_text_field(Data::getRequest('value')));
                $newValue = Data::getResursOption($storeAsKey);
            }
        }

        $result = [
            'allowed' => isset($paymentMethod->id) ? true : false,
            'newValue' => $newValue,
        ];

        self::reply(
            $result
        );
    }

    /**
     * @since 0.0.1.5
     * @noinspection OffsetOperationsInspection
     */
    public static function updatePaymentMethodFee()
    {
        $allowed = false;
        if (is_admin() && is_ajax() && Data::isPaymentFeeAllowed()) {
            $id = explode('_', Data::getRequest('id'));
            $newValue = '';

            if (isset($id[2]) && !empty($id[2])) {
                $paymentMethod = Data::getPaymentMethodById($id[2]);
                $allowed = isset($paymentMethod->id) ? true : false;

                $requestValue = Data::getRequest('value');
                if (is_object($paymentMethod) && is_numeric($requestValue)) {
                    $storeAsKey = sprintf('method_custom_fee_%s', $paymentMethod->id);
                    Data::setResursOption($storeAsKey, (float)$requestValue);
                    $newValue = Data::getResursOption($storeAsKey);
                } else {
                    $allowed = false;
                }
            }
        }

        $result = [
            'allowed' => $allowed,
            'newValue' => $newValue,
        ];

        self::reply(
            $result
        );
    }

    /**
     * @return array
     * @since 0.0.1.0
     */
    public static function getTriggerResponse(): array
    {
        $runTime = 0;
        $success = false;
        if (isset($_REQUEST['runTime'])) {
            $runTime = (int)$_REQUEST['runTime'];
        }
        sleep(1);
        $testCallbackStarted = Data::getResursOption('resurs_callback_test_start');
        $lastTestResponse = self::getTestCallbackLastResponse();
        if ($lastTestResponse !== '' && $lastTestResponse !== 1) {
            $lastResponse = self::getTestCallbackLastResponse(false);
            $success = true;
        } elseif ($testCallbackStarted && $lastTestResponse !== 1) {
            $lastResponse =
                sprintf(
                    __(
                        'Waiting for callback TEST (%d seconds).'
                    ),
                    $runTime
                );
        } else {
            $lastResponse = self::getTestCallbackLastResponse(false);
        }
        $return = [
            'lastResponse' => $lastResponse,
            'runTime' => $runTime,
            'success' => $success,
        ];

        return $return;
    }

    /**
     * @since 0.0.1.4
     */
    private function getTestCallbackLastResponse($int = true)
    {
        $lastTestResponseString = Data::getResursOption('resurs_callback_test_response');
        if ((int)$lastTestResponseString === 1) {
            return __(
                'Waiting.',
                'resurs-bank-payments-for-woocommerce'
            );
        }
        return $int ? (int)$lastTestResponseString : sprintf(
            '%s %s',
            __('Received', 'resurs-bank-payments-for-woocommerce'),
            date('Y-m-d H:i:s', (int)$lastTestResponseString)
        );
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getAddress(): void
    {
        $addressResponse = [];
        $identification = Data::getRequest('identification');
        $customerType = Data::getCustomerType();
        $customerCountry = Data::getCustomerCountry();

        $return = [
            'api_error' => '',
            'code' => 0,
            'identificationResponse' => [],
        ];

        if (!Data::hasCredentials()) {
            $return['api_error'] = __(
                'Service is currently not active.',
                'resurs-bank-payments-for-woocommerce'
            );
            self::reply($return);
        }

        WooCommerce::setSessionValue('identification', Data::getRequest('identification'));

        // @todo Investigate getAddressByPhoneNumber (SOAP) if it will ever return to MAPI.
        // @todo If not, this switch will be better off as an if.
        switch ($customerCountry) {
            case 'SE':
                try {
                    // This request is a display-only solution, so it has to be returned as an array so
                    // that the fron script can fetch it.
                    $addressResponse = CustomerRepoitory::getAddress(
                        StoreId::getData(),
                        $identification,
                        $customerType,
                    )->toArray();
                } catch (Exception $e) {
                    Config::getLogger()->error(
                        sprintf(
                            '%s failed for %s with message %s',
                            __FUNCTION__,
                            $identification,
                            $e->getMessage()
                        )
                    );
                    $return['api_error'] = $e->getMessage();
                    $return['code'] = $e->getCode();
                }
                break;
            default:
        }
        $return['identificationResponse'] = $addressResponse;
        $return['billing_country'] = $customerCountry;

        // Make sure we really got a valid content from the response since Resurs may return empties on
        // API-errors even if the government id is valid and working. So we can not just verify that the response
        // is an array, we also have to make sure it has content.
        if (count($addressResponse)) {
            $return = self::getTransformedAddressResponse($return, $addressResponse);
        }

        self::reply($return);
    }

    /**
     * Log an event of getAddress.
     *
     * @param string $customerCountry
     * @param CustomerType $customerType
     * @param string $identification
     * @param string $runFunctionInfo
     * @since 0.0.1.0
     */
    private static function getAddressLog(
        string $customerCountry,
        CustomerType $customerType,
        string $identification,
        string $runFunctionInfo
    ): void {
        Data::writeLogEvent(
            Data::CAN_LOG_ORDER_EVENTS,
            sprintf(
                __(
                    'getAddress request (country %s, type %s) for %s: %s',
                    'resurs-bank-payments-for-woocommerce'
                ),
                $customerCountry,
                $customerType->value,
                $identification,
                $runFunctionInfo
            )
        );
    }

    /**
     * Transform getAddress responses into WooCommerce friendly data fields so that they
     * can be easily pushed out to the default forms.
     * @param $return
     * @param $addressResponse
     * @return mixed
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function getTransformedAddressResponse($return, $addressResponse)
    {
        $compileKeys = [];
        $compileInfo = [];
        $addressFields = WordPress::applyFilters('getAddressFieldController', []);
        foreach ($addressFields as $addressField => $addressTransform) {
            // Check if the session is currently holding something that we want to put up in some fields.
            $wooSessionData = trim(Data::getRequest($addressTransform));
            if (!empty($wooSessionData)) {
                $addressResponse[$addressTransform] = $wooSessionData;
            }
            if (preg_match('/:/', $addressTransform)) {
                $splitInfo = explode(':', $addressTransform);
                foreach ($splitInfo as $splitKey) {
                    $compileInfo[] = '%s';
                    $compileKeys[] = $addressResponse[$splitKey] ?? '';
                }
                $return[$addressField] = vsprintf(implode(' ', $compileInfo), $compileKeys);
            } else {
                $return[$addressField] = $addressResponse[$addressTransform] ?? '';
            }
        }

        return $return;
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function purchaseReject(): array
    {
        $return = [
            'error' => false,
            'rejectUpdate' => false,
            'message' => '',
        ];
        $rejectType = Data::getRequest('type');

        // Presumably this is available for us to handle the order with.
        $wooOrderId = WooCommerce::getSessionValue('order_awaiting_payment');

        $transReject = [
            'fail' => __('Failed', 'resurs-bank-payments-for-woocommerce'),
            'deny' => __('Denied', 'resurs-bank-payments-for-woocommerce'),
        ];

        // Fallback to the standard reject reason if nothing is found.
        $transRejectMessage = $transReject[$rejectType] ?? $rejectType;

        if ($wooOrderId) {
            $currentOrder = new WC_Order($wooOrderId);
            $failNote = sprintf(
                __('Order was rejected with status "%s".', 'resurs-bank-payments-for-woocommerce'),
                $transRejectMessage
            );
            $updateStatus = WooCommerce::setOrderStatusUpdate(
                $currentOrder,
                'failed',
                $failNote
            );
            Data::writeLogNotice(
                $failNote
            );

            // Insert new row each time this section is triggered. That makes the try count traceable.
            Data::setOrderMeta($currentOrder, 'rco_rejected_once', true);
            Data::setOrderMeta(
                $currentOrder,
                'rco_rejected',
                sprintf(
                    '%s (%s)',
                    $rejectType,
                    date('Y-m-d H:i:s', time())
                ),
                true,
                true
            );

            // When status update is finished, add more information since it goes out to customer front too.
            $failNote .= ' ' .
                WordPress::applyFilters(
                    'purchaseRejectCustomerMessage',
                    __(
                        'Please contact customer service for more information.',
                        'resurs-bank-payments-for-woocommerce'
                    )
                );

            Data::setOrderMeta($currentOrder, 'rco_reject_message', $failNote);

            $return['rejectUpdate'] = $updateStatus;
            $return['message'] = $failNote;
            return $return;
        }

        return $return;
    }

    /**
     * @since 0.0.1.4
     */
    public static function resetPluginSettings()
    {
        global $wpdb;
        self::getValidatedNonce(false, false, __FUNCTION__);
        // Double insurances here, since we're about to reset stuff.
        if (is_admin() && is_ajax()) {
            $deleteNot = [
                'admin_iv',
                'admin_key',
            ];
            $deleteNotArray = [];
            foreach ($deleteNot as $item) {
                $deleteNotArray[] = sprintf("option_name != '%s_%s'", Data::getPrefix(), $item);
            }

            // Clean up old importer data.
            Data::delResursOption('resursImportCredentials');
            // Make sure that both original prefixes and special adapted slugs are cleaned up.
            $deleteArray = [
                sprintf(
                    "DELETE FROM %s WHERE option_name LIKE '%s_%%' AND %s",
                    Data::getSanitizedKeyElement($wpdb->options),
                    Data::getPrefix(),
                    implode(' AND ', $deleteNotArray)
                ),
                sprintf(
                    "DELETE FROM %s WHERE option_name LIKE '%s_%%' AND %s",
                    Data::getSanitizedKeyElement($wpdb->options),
                    Data::getPrefix('admin', true),
                    implode(' AND ', $deleteNotArray)
                )
            ];

            foreach ($deleteArray as $deleteString) {
                $cleanUpQuery = $wpdb->query($deleteString);
            }

            self::reply(
                [
                    'finished' => $cleanUpQuery,
                ]
            );
        }
    }

    /**
     * @since 0.0.1.7
     */
    public static function resetOldPluginSettings()
    {
        global $wpdb;
        self::getValidatedNonce(false, false, __FUNCTION__);
        // Double insurances here, since we're about to reset stuff.
        if (is_admin() && is_ajax()) {
            $cleanUpQuery = $wpdb->query(
                sprintf(
                    "DELETE FROM %s WHERE option_name 
                         LIKE 'woocommerce_resurs-bank_%%' OR option_name like 'woocommerce_resurs_bank%%'",
                    $wpdb->options
                )
            );

            self::reply(
                [
                    'finished' => $cleanUpQuery,
                ]
            );
        }
    }
}
