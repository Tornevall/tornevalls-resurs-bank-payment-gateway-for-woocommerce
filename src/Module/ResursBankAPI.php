<?php

namespace ResursBank\Module;

use Exception;
use Resursbank\Ecommerce\Service\Merchant\MerchantApi;
use Resursbank\Ecommerce\Service\Merchant\ResursToken;
use Resursbank\RBEcomPHP\RESURS_ENVIRONMENTS;
use Resursbank\RBEcomPHP\ResursBank;
use ResursBank\Service\WooCommerce;
use ResursException;
use RuntimeException;
use TorneLIB\Exception\Constants;
use function count;
use function in_array;
use function is_array;
use function is_object;

/**
 * Class Api EComPHP Translator. Also a guide for what could be made better in the real API.
 * @package ResursBank\Module
 * @since 0.0.1.0
 */
class ResursBankAPI
{
    /**
     * @var ResursBank $resursBank
     */
    public static $resursBank;

    /**
     * @var array $paymentMethods
     * @since 0.0.1.0
     */
    private static $paymentMethods;

    /**
     * @var array $callbacks
     * @since 0.0.1.0
     */
    private static $callbacks;

    /**
     * @var array $annuityFactors
     * @since 0.0.1.0
     */
    private static $annuityFactors;

    /**
     * @var ResursBank $ecom
     * @since 0.0.1.0
     */
    private $ecom;

    /**
     * @var array $credentials
     * @since 0.0.1.0
     */
    private $credentials = [
        'username' => '',
        'password' => '',
    ];

    /**
     * Static variable of Resurs Bank Merchant API (early bleeding edge state).
     * @var MerchantApi
     * @since 0.0.1.0
     */
    private static $merchantApi;

    /**
     * Resurs Bank Merchant API (early bleeding edge state).
     * @var MerchantApi
     * @since 0.0.1.0
     */
    private $merchantConnection;

    /**
     * @return bool|string
     * @since 0.0.1.0
     */
    public static function getWsdlMode()
    {
        return Data::getResursOption('api_wsdl');
    }

    /**
     * Get payment properly by testing two API's before giving up.
     * @param $orderId
     * @param null $failOver
     * @param null $orderInfo
     * @return mixed
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getPayment($orderId, $failOver = null, $orderInfo = null)
    {
        if ((bool)$failOver) {
            self::getResurs()->setFlag('GET_PAYMENT_BY_REST');
        }
        try {
            $credentialMeta = self::getMatchingCredentials($orderInfo);
            if ($credentialMeta !== null) {
                $resurs = self::getEcomBySecondaryCredentials($credentialMeta, $orderId);
                $return = $resurs->getPayment($orderId);
                $return->isCurrentCredentials = false;
                $return->username = $credentialMeta->l ?? '';
                $return->environment = $credentialMeta->e ?? 1;
            } else {
                $return = self::getResurs()->getPayment($orderId);
                $return->isCurrentCredentials = true;
                $return->username = null;
                $return->environment = null;
            }
        } catch (Exception $e) {
            Data::setTimeoutStatus(self::getResurs(), $e);
            Data::setLogException($e);
            // Do not check the timeout handler here, only check if the soap request has timed out.
            // On other errors, just pass the exception on, since there may something worse than just
            // a timeout.
            if (!(bool)$failOver && $e->getCode() === Constants::LIB_NETCURL_SOAP_TIMEOUT) {
                self::getPaymentByRestNotice($e);
                return self::getPayment($orderId, true);
            }
            throw $e;
        }
        // Restore.
        self::getResurs()->deleteFlag('GET_PAYMENT_BY_REST');

        return $return;
    }

    /**
     * @return ResursBank
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getResurs(): ResursBank
    {
        if (empty(self::$resursBank)) {
            // Instantiation.
            self::$resursBank = new self();
        }

        return self::$resursBank->getConnection();
    }

    /**
     * @return MerchantApi
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getMerchantConnection(): MerchantApi
    {
        if (empty(self::$merchantApi)) {
            // Instantiation.
            self::$merchantApi = new self();
        }

        return self::$merchantApi->getMerchantApi();
    }

    /**
     * @return MerchantApi
     * @throws Exception
     */
    public function getMerchantApi(): MerchantApi
    {
        if (Data::isBleedingEdge() && Data::isBleedingEdgeApiReady()) {
            try {
                if (empty($this->merchantConnection)) {
                    $livingTransientToken = (string)get_transient(
                        sprintf('%s_bearer', Data::getPrefix())
                    );

                    if (empty($livingTransientToken)) {
                        $this->merchantConnection = (new MerchantApi())
                            ->setClientId(Data::getResursOption('jwt_client_id'))
                            ->setClientSecret(Data::getResursOption('jwt_client_password'))
                            ->setScope('mock-merchant-api')
                            ->setGrantType('client_credentials');

                        $tokenRequestData = $this->merchantConnection->getToken();
                        if ($tokenRequestData instanceof ResursToken && $tokenRequestData->getAccessToken() !== '') {
                            Data::setDeveloperLog(
                                __FUNCTION__,
                                __(
                                    'Merchant API token renewed.',
                                    'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                                )
                            );
                            $livingTransientToken = $tokenRequestData->getAccessToken();
                            set_transient(
                                sprintf('%s_bearer', Data::getPrefix()),
                                $livingTransientToken,
                                $tokenRequestData->getExpire()
                            );
                        }
                    } else {
                        $this->merchantConnection = (new MerchantApi())->setBearer($livingTransientToken);
                        Data::setDeveloperLog(
                            __FUNCTION__,
                            __(
                                'Merchant API token reused.',
                                'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                            )
                        );
                    }
                }
            } catch (Exception $e) {
                Data::setLogException($e);
            }
        }

        return $this->merchantConnection;
    }

    /**
     * @return ResursBank
     * @throws Exception
     * @since 0.0.1.0
     */
    public function getConnection(): ResursBank
    {
        $timeoutStatus = Data::getTimeoutStatus();
        if (empty($this->ecom)) {
            $this->getResolvedCredentials();
            $this->ecom = new ResursBank(
                $this->credentials['username'],
                $this->credentials['password'],
                self::getEnvironment()
            );
        }

        if (!empty(Data::getResursOption('api_soap_url'))) {
            $soapUrl = sprintf('%s/', preg_replace('/\/$/', '', Data::getResursOption('api_soap_url')));
            if (!empty($soapUrl) && self::getEnvironment() === RESURS_ENVIRONMENTS::TEST) {
                $this->ecom->setTestUrl(
                    $soapUrl,
                    Data::getCheckoutType()
                );
            }
        }

        $this->setWsdlCache();
        $this->setEcomConfiguration();
        $this->setEcomTimeout();
        $this->setEcomAutoDebitMethods();

        if ($timeoutStatus > 0) {
            $this->setEcomTimeout(4);
        }

        return $this->ecom;
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    private function getResolvedCredentials(): bool
    {
        $environment = Data::getResursOption('environment');

        if ($environment === 'live') {
            $getUserFrom = 'login_production';
            $getPasswordFrom = 'password_production';
        } else {
            $getUserFrom = 'login';
            $getPasswordFrom = 'password';
        }

        $this->credentials['username'] = Data::getResursOption($getUserFrom);
        $this->credentials['password'] = Data::getResursOption($getPasswordFrom);

        if (empty($this->credentials['username']) || empty($this->credentials['password'])) {
            throw new RuntimeException('ECom credentials are not fully set.', 404);
        }

        return true;
    }

    /**
     * @return bool
     * @since 0.0.1.0
     */
    public static function getEnvironment(): bool
    {
        return in_array(
            Data::getResursOption('environment'),
            [
                'live',
                'production',
            ],
            true
        ) ? RESURS_ENVIRONMENTS::PRODUCTION : RESURS_ENVIRONMENTS::TEST;
    }

    /**
     * @return string
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setWsdlCache(): string
    {
        $wsdlMode = Data::getResursOption('api_wsdl');

        // If production is set, we'll go cached wsdl to speed up in default view.
        switch ($wsdlMode) {
            case 'none':
                $this->ecom->setWsdlCache(false);
                break;
            case 'both':
                $this->ecom->setWsdlCache(true);
                break;
            default:
                // Default: Is production?
                if (!Data::getTestMode()) {
                    $this->ecom->setWsdlCache(true);
                }
                break;
        }

        return $wsdlMode;
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setEcomConfiguration()
    {
        $this->ecom->setUserAgent(
            sprintf(
                '%s_%s_%s',
                Data::getPluginTitle(true),
                Data::getCurrentVersion(),
                Data::getCheckoutType()
            )
        );
    }

    /**
     * @param int $forceTimeout
     * @return $this
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setEcomTimeout($forceTimeout = null): self
    {
        $this->ecom->setFlag('CURL_TIMEOUT', Data::getDefaultApiTimeout($forceTimeout));

        return $this;
    }

    /**
     * @return $this
     * @since 0.0.1.0
     */
    private function setEcomAutoDebitMethods(): self
    {
        $finalizationMethodTypes = (array)Data::getResursOption('order_instant_finalization_methods');

        foreach ($finalizationMethodTypes as $methodType) {
            if ($methodType !== 'default') {
                $this->ecom->setAutoDebitableType($methodType);
            }
        }
        return $this;
    }

    /**
     * Compare current credential setup with order meta credentials.
     *
     * Inspection says not all necessary throws tags are here. But they are.
     * @param $orderInfo
     * @return bool
     * @throws ResursException
     * @since 0.0.1.0
     */
    private static function getMatchingCredentials($orderInfo)
    {
        $credentialMeta = json_decode(self::getApiMeta($orderInfo), false);
        $return = null;

        $intersected = array_intersect(
            (array)$credentialMeta,
            [
                'l' => Data::getResursOption('login'),
                'p' => Data::getResursOption('password'),
                'e' => Data::getResursOption('environment'),
            ]
        );

        if (count($intersected) !== count((array)$credentialMeta)) {
            $return = $credentialMeta;
        }

        return $return;
    }

    /**
     * @param $orderData
     * @return false|string
     * @throws ResursException
     * @since 0.0.1.0
     * @noinspection SpellCheckingInspection
     */
    private static function getApiMeta($orderData)
    {
        $return = Data::getDecryptData(
            Data::getOrderMeta('orderapi', $orderData)
        );

        if (empty($return)) {
            // Encryption may have failed.
            $return = Data::getDecryptData(Data::getOrderMeta('orderapi', $orderData), true);
        }

        return $return;
    }

    /**
     * @param $credentialMeta
     * @param $orderId
     * @return ResursBank
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function getEcomBySecondaryCredentials($credentialMeta, $orderId): ResursBank
    {
        Data::setLogNotice(
            sprintf(
                __(
                    'Ecom request %s for %s with different credentials (%s, in environment %s).',
                    'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                ),
                __FUNCTION__,
                $orderId,
                $credentialMeta->l,
                in_array($credentialMeta->e, ['test', 'staging']) ? 1 : 0
            )
        );
        return self::getTemporaryEcom(
            $credentialMeta->l ?? '',
            $credentialMeta->p ?? '',
            in_array($credentialMeta->e, ['test', 'staging']) ? 1 : 0
        );
    }

    /**
     * @param $username
     * @param $password
     * @param $environment
     * @return ResursBank
     * @throws Exception
     * @since 0.0.1.0
     */
    private static function getTemporaryEcom($username, $password, $environment): ResursBank
    {
        // Creating a simple connection.
        return new ResursBank(
            $username,
            $password,
            $environment
        );
    }

    /**
     * @param Exception $e
     * @since 0.0.1.0
     */
    private static function getPaymentByRestNotice($e)
    {
        Data::setLogNotice(
            sprintf(
                __(
                    'Got exception %d in %s, will retry with REST.',
                    'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                ),
                $e->getCode(),
                __FUNCTION__
            )
        );
    }

    /**
     * Fetch annuity factors from storage or new data.
     * @param $fromStorage
     * @return array|int|mixed|null
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    public static function getAnnuityFactors($fromStorage = true)
    {
        $return = self::$annuityFactors;
        $stored = json_decode(Data::getResursOption('annuityFactors'), false);
        if ($fromStorage && !empty($stored)) {
            $return = $stored;
        }

        if (!$fromStorage || empty($return)) {
            $annuityArray = [];

            try {
                $paymentMethods = (array)self::getPaymentMethods($fromStorage);
                if (Data::canMock('annuityFactorConfigException', false)) {
                    // This mocking section will simulate a total failure of the fetching part.
                    $stored = null;
                    WooCommerce::applyMock('annuityFactorConfigException');
                }
                /** @noinspection CallableParameterUseCaseInTypeContextInspection */
                if (count($paymentMethods)) {
                    foreach ($paymentMethods as $paymentMethod) {
                        $annuityResponse = self::getResurs()->getAnnuityFactors($paymentMethod->id);
                        if (is_array($annuityResponse) || is_object($annuityResponse)) {
                            // Are we running side by side with v2.x?
                            // And is that side running ECom 1.3.41 or higher?
                            $annuityArray[$paymentMethod->id] = is_array($annuityResponse) ?
                                $annuityResponse : [$annuityResponse];
                        }
                    }
                    self::$annuityFactors = $annuityArray;
                }
            } catch (Exception $e) {
                if (is_object($stored)) {
                    WooCommerce::setSessionValue('silentAnnuityException', $e);
                    // If there are errors during this procedure, override the stored controller
                    // and return data if there is a cached storage. This makes it possible for the
                    // plugin to live without the access to Resurs Bank.
                    self::$annuityFactors = $stored;
                } else {
                    Data::setTimeoutStatus(self::getResurs());
                    throw $e;
                }
            }
            $return = self::$annuityFactors;
            Data::setResursOption('annuityFactors', json_encode(self::$annuityFactors));
        }
        WooCommerce::setSessionValue('silentAnnuityException', null);

        return $return;
    }

    /**
     * Fetch stored or new payment methods.
     * @param bool $fromStorage
     * @return array|mixed
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    public static function getPaymentMethods($fromStorage = true)
    {
        // Mocking feature that reveals that this payment methods request is effective all around
        // the admin platform. When this mock is triggered, other pages (like the mock-admin) may
        // fail. This feature turned out to fix an important bug. We also knows that requesting payment
        // methods can also affect admin panel if Resurs Bank throws an exception if the method request
        // is failing at this point (since we're cleaning up the storage).
        if (isset($_REQUEST['section']) &&
            $_REQUEST['section'] === 'payment_methods' &&
            !WooCommerce::getSessionValue('rb_requesting_debit_methods') &&
            Data::canMock('getEmptyPaymentMethodsException')
        ) {
            // But this feature is only set for visual testing.
            // Note: As the wp-admin is running, we will first pass through "auto debitable methods".
            Data::setResursOption('paymentMethods', null);
        }

        $return = self::$paymentMethods;
        if ($fromStorage) {
            /** @noinspection JsonEncodingApiUsageInspection */
            $stored = json_decode(Data::getResursOption('paymentMethods'));
            if (is_array($stored)) {
                $return = $stored;
            }
        }

        if (!$fromStorage || empty($return)) {
            try {
                if (isset($_REQUEST['section']) && $_REQUEST['section'] === 'payment_methods') {
                    // Also this part is used for the specific admin section.
                    WooCommerce::applyMock('getPaymentMethodsException');
                }
                self::$paymentMethods = self::getResurs()->getPaymentMethods([], true);
            } catch (Exception $e) {
                Data::setTimeoutStatus(self::getResurs());
                WooCommerce::setSessionValue('silentGetPaymentMethodsException', $e);
                // We do not want to reset on errors, right?
                //Data::setResursOption('paymentMethods', null);
                throw $e;
            }
            WooCommerce::setSessionValue('silentGetPaymentMethodsException', null);
            $return = self::$paymentMethods;
            Data::setResursOption('paymentMethods', json_encode(self::$paymentMethods));
        }

        return $return;
    }

    /**
     * @param bool $fromStorage
     * @return array|mixed
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    public static function getCallbackList($fromStorage = true)
    {
        $return = self::$callbacks;
        $stored = json_decode(Data::getResursOption('callbacks'), false);
        if ($fromStorage && is_array($stored)) {
            $return = $stored;
        }

        if (!$fromStorage || empty($return)) {
            try {
                WooCommerce::applyMock('callbackUpdateException');
                self::$callbacks = self::getResurs()->getRegisteredEventCallback(255);
            } catch (Exception $e) {
                if (is_object($stored) || is_array($stored)) {
                    // If there are errors during this procedure, override the stored controller
                    // and return data if there is a cached storage. This makes it possible for the
                    // plugin to live without the access to Resurs Bank.
                    self::$callbacks = (array)$stored;
                } else {
                    Data::setTimeoutStatus(self::getResurs(), $e);
                    // We do not want to reset on errors, right?
                    //Data::setResursOption('callbacks', null);
                    throw $e;
                }
            }
            $return = self::$callbacks;
            Data::setResursOption('callbacks', json_encode(self::$callbacks));
        }

        return $return;
    }

    /**
     * @return bool
     * @throws Exception
     * @since 0.0.1.0
     */
    public function getCredentialsPresent(): bool
    {
        $return = true;
        try {
            $this->getResolvedCredentials();
        } catch (Exception $e) {
            Data::setTimeoutStatus(self::getResurs(), $e);
            Data::setLogException($e);
            $return = false;
        }
        return $return;
    }

    /**
     * @param $checkoutType
     * @throws Exception
     * @since 0.0.1.0
     */
    public function setCheckoutType($checkoutType)
    {
        $this->getConnection()->setPreferredPaymentFlowService($checkoutType);
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    public function setFraudFlags()
    {
        $this->getConnection()->setWaitForFraudControl(Data::getResursOption('waitForFraudControl'));
        $this->getConnection()->setAnnulIfFrozen(Data::getResursOption('waitForFraudControl'));
        $this->getConnection()->setFinalizeIfBooked(Data::getResursOption('waitForFraudControl'));
    }
}
