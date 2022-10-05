<?php

namespace ResursBank\Module;

use Exception;
use JsonException;
use ReflectionException;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\CacheException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\FormatException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Cache\None;
use Resursbank\Ecom\Lib\Log\FileLogger;
use Resursbank\Ecom\Lib\Network\Model\Auth\Jwt;
use Resursbank\Ecom\Module\PaymentMethod\Repository;
use Resursbank\Ecom\Module\Store\Models\Store;
use Resursbank\Ecom\Module\Store\Models\StoreCollection;
use Resursbank\Ecom\Module\Store\Repository as StoreRepository;
use ResursBank\Exception\MapiCredentialsException;
use ResursBank\Exception\StoreException;
use ResursBank\Exception\WooCommerceException;
use Resursbank\RBEcomPHP\RESURS_ENVIRONMENTS;
use Resursbank\RBEcomPHP\ResursBank;
use ResursBank\Service\WooCommerce;
use ResursBank\Service\WordPress;
use ResursException;
use stdClass;
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
     * @since 0.0.1.0
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
    private static $annuityFactors = [];
    /**
     * @var StoreCollection $storeCollection
     */
    private static StoreCollection $storeCollection;
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
        'jwt_client_id' => '',
        'jwt_client_secret' => '',
    ];

    /**
     * @throws EmptyValueException
     * @throws FilesystemException
     * @throws FormatException
     * @throws MapiCredentialsException
     * @throws WooCommerceException
     */
    public function __construct()
    {
        try {
            $this->getConnection();
        } catch (Exception $connectionException) {
            // Ignore on failures at this point, since we must be able to reach places
            // where the connection is not necessary. For example, if no credentials are set
            // when trying to reach wp-admin, section will make the platform bail out.
            if (is_admin()) {
                // If we are in wp-admin, we allow an error message to be shown on screen.
                WordPress::setGenericError($connectionException);
            }
        }
    }

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
            if (isset($orderInfo)) {
                $credentialMeta = self::getMatchingCredentials($orderInfo);
                if (isset($credentialMeta) && !empty($credentialMeta)) {
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
            }
        } catch (Exception $e) {
            Data::setTimeoutStatus(self::getResurs(), $e);
            Data::setLogException($e, __FUNCTION__);
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

        return isset($return) && !empty($return) ? $return : new stdClass();
    }

    /**
     * @return ResursBankAPI|ResursBank
     * @throws Exception
     * @since 0.0.1.0
     * @todo Fix the return type (or use void).
     */
    public static function getResurs(): ResursBank|ResursBankAPI
    {
        if (empty(self::$resursBank)) {
            // Instantiation.
            self::$resursBank = new self();
        }

        return self::$resursBank;
    }

    /**
     * @return string
     * @since 0.0.1.9
     */
    private function getClientId(): string
    {
        return Data::getResursOption('environment') === 'test' ?
            Data::getResursOption('jwt_client_id') : Data::getResursOption('jwt_client_id_production');
    }

    /**
     * @return string
     * @since 0.0.1.9
     */
    private function getClientSecret(): string
    {
        return Data::getResursOption('environment') === 'test' ?
            Data::getResursOption('jwt_client_secret') : Data::getResursOption('jwt_client_secret_production');
    }

    /**
     * @return void
     * @throws EmptyValueException
     * @throws MapiCredentialsException
     * @throws WooCommerceException
     * @throws FilesystemException
     * @throws FormatException
     * @since 0.0.1.0
     */
    public function getConnection(): void
    {
        // @todo Make sure the scope for production is correct.
        $scope = Data::getResursOption('environment') === 'test' ? 'mock-merchant-api' : 'merchant-api';
        $grantType = 'client_credentials';

        if (empty(self::getClientId()) || empty(self::getClientSecret())) {
            throw new MapiCredentialsException(
                message: 'Credentials not set.'
            );
        }

        if (!defined('WC_LOG_DIR')) {
            throw new WooCommerceException('Can not find WooCommerce in this platform.');
        }

        Config::setup(
            logger: new FileLogger(path: WooCommerce::getWcLogDir()),
            cache: new None(),
            jwtAuth: new Jwt(
                clientId: $this->getClientId(),
                clientSecret: $this->getClientSecret(),
                scope: $scope,
                grantType: $grantType
            )
        );
    }

    /**
     * @return bool
     * @throws Exception
     * @since 0.0.1.9
     */
    private function getResolvedCredentials(): bool
    {
        // Make sure we still get credentials for the API specifically.
        if (Data::hasCredentials()) {
            $this->credentials = Data::getResolvedCredentialData();
        }

        // Keep handling exceptions as before.
        return Data::getResolvedCredentials();
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
     * @return mixed|null
     * @throws ResursException
     * @since 0.0.1.0
     * @noinspection SpellCheckingInspection
     */
    private static function getApiMeta($orderData)
    {
        if (isset($orderData)) {
            $return = Data::getDecryptData(
                Data::getOrderMeta('orderapi', $orderData)
            );

            if (!isset($return) || empty($return)) {
                // Encryption may have failed.
                $return = Data::getDecryptData(Data::getOrderMeta('orderapi', $orderData), true);
            }
        } else {
            $return = '';
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
                        if ($paymentMethod->apiType !== 'SOAP') {
                            continue;
                        }
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
                if ($e->getCode() === 8) {
                    // Ignore this error, as it may be caused by wrong API.
                } elseif (is_object($stored)) {
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
        $return = self::$paymentMethods;
        if ($fromStorage && !empty(Data::getResursOption('paymentMethods'))) {
            try {
                $return = json_decode(
                    Data::getResursOption('paymentMethods'),
                    associative: false,
                    flags: JSON_THROW_ON_ERROR
                );
            } catch (Exception) {
            }
        }

        if (Data::getStoreId() > 0 && (!$fromStorage || empty($return))) {
            self::$paymentMethods = Repository::getPaymentMethods(self::getStoreUuidByNationalId(Data::getStoreId()))->toArray();
            Data::setResursOption('lastMethodUpdate', time());
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
        if ($fromStorage && (is_array($stored) || is_object($stored))) {
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

        return (array)$return;
    }

    /**
     * Render an array with available stores at Resurs. This is used to automatically generate an options
     * list for the API stores.
     *
     * @return array
     * @throws ApiException
     * @throws AuthException
     * @throws CacheException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws ValidationException
     * @throws JsonException
     * @throws ReflectionException
     * @since 0.0.1.9
     */
    public static function getRenderedStores(): array
    {
        $storeArray = [];

        $storeCollection = self::getStoreCollection();
        foreach ($storeCollection as $store) {
            $storeArray[$store->nationalStoreId] = sprintf('(%s) %s', $store->nationalStoreId, $store->name);
        }

        return $storeArray;
    }

    /**
     * @return array
     * @throws ApiException
     * @throws AuthException
     * @throws CacheException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ValidationException
     * @since 0.0.1.9
     */
    public static function getStoreCollection(): array
    {
        if (empty(self::$storeCollection)) {
            self::$storeCollection = StoreRepository::getStores();
        }

        return self::$storeCollection->toArray();
    }

    /**
     * @param int $storeId
     * @return string
     * @throws ApiException
     * @throws AuthException
     * @throws CacheException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws StoreException
     * @throws ValidationException
     * @since 0.0.1.9
     */
    public static function getStoreUuidByNationalId(int $storeId): string
    {
        $return = '';
        /** @var Store $store */
        $storeCollection = self::getStoreCollection();
        foreach ($storeCollection as $store) {
            if ($store->nationalStoreId === $storeId) {
                $return = $store->id;
                break;
            }
        }

        if (empty($return)) {
            throw new StoreException('Store could not be found.');
        }

        return $return;
    }

    /**
     * String to identify environment and credentials.
     * @return string
     * @throws Exception
     * @since 0.0.1.6
     */
    public function getCredentialString(): string
    {
        $this->getResolvedCredentials();

        return sprintf(
            '%s_%s',
            Data::isTest() ? 'test' : 'live',
            $this->credentials['jwt_client_id']
        );
    }


    ///////////////////// MAPI Based Features that is supposed to replace SOAP ///////////////////////

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
            $return = false;
            if ($e->getCode() !== Data::UNSET_CREDENTIALS_EXCEPTION) {
                Data::setTimeoutStatus(self::getResurs(), $e);
                Data::setLogException($e, __FUNCTION__);
            }
        }
        return $return;
    }

    /**
     * @param $checkoutType
     * @throws Exception
     * @since 0.0.1.0
     * @todo Flow selecting for simplified / RCO.
     */
    public function setCheckoutType($checkoutType)
    {
        //$this->getConnection()->setPreferredPaymentFlowService($checkoutType);
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     * @todo Adapt for ecom2.
     */
    public function setFraudFlags()
    {
        //$this->getConnection()->setWaitForFraudControl(Data::getResursOption('waitForFraudControl'));
        //$this->getConnection()->setAnnulIfFrozen(Data::getResursOption('waitForFraudControl'));
        //$this->getConnection()->setFinalizeIfBooked(Data::getResursOption('waitForFraudControl'));
    }
}
