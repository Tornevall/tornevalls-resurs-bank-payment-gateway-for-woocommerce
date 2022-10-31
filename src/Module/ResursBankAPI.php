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
use Resursbank\Ecom\Lib\Locale\Locale;
use Resursbank\Ecom\Lib\Log\FileLogger;
use Resursbank\Ecom\Lib\Log\NoneLogger;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Lib\Network\Model\Auth\Jwt;
use Resursbank\Ecom\Module\Payment\Repository as PaymentRepository;
use Resursbank\Ecom\Module\PaymentMethod\Repository;
use Resursbank\Ecom\Module\Store\Repository as StoreRepository;
use Resursbank\Ecom\Module\Store\Models\Store;
use Resursbank\Ecom\Module\Store\Models\StoreCollection;
use ResursBank\Exception\MapiCredentialsException;
use ResursBank\Exception\StoreException;
use Resursbank\RBEcomPHP\RESURS_ENVIRONMENTS;
use Resursbank\RBEcomPHP\ResursBank;
use ResursBank\Service\WooCommerce;
use ResursBank\Service\WordPress;
use ResursException;
use stdClass;
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
     * @return void
     * @throws EmptyValueException
     * @throws FormatException
     * @throws MapiCredentialsException
     * @throws Exception
     * @since 0.0.1.0
     */
    public function getConnection(): void
    {
        // @todo Make sure the scope for production is correct.
        $scope = Data::getResursOption('environment') === 'test' ? 'mock-merchant-api' : 'merchant-api';
        $grantType = 'client_credentials';

        if (isset(Config::$instance)) {
            return;
        }

        // Default logs to no writer, in case we don't have a logger available.
        $fileLogger = new NoneLogger();

        // Check if the proper logger is available.
        if (WooCommerce::getPluginLogDir()) {
            try {
                $fileLogger = new FileLogger(WooCommerce::getPluginLogDir());
            } catch (FilesystemException $e) {
                WordPress::setGenericError($e);
            }
        }

        if (empty(self::getClientId()) || empty(self::getClientSecret())) {
            throw new MapiCredentialsException(
                message: 'Credentials not set.'
            );
        }

        switch (Data::getCustomerCountry()) {
            case 'SE':
                $locale = Locale::sv;
                break;
            default:
                $locale = Locale::en;
        }

        Config::setup(
            logger: $fileLogger,
            cache: new None(),
            jwtAuth: new Jwt(
                clientId: $this->getClientId(),
                clientSecret: $this->getClientSecret(),
                scope: $scope,
                grantType: $grantType
            ),
            locale: $locale
        );
    }

    /**
     * @return string
     */
    private function getClientId(): string
    {
        return Data::getResursOption('environment') === 'test' ?
            Data::getResursOption('jwt_client_id') : Data::getResursOption('jwt_client_id_production');
    }

    /**
     * @return string
     */
    private function getClientSecret(): string
    {
        return Data::getResursOption('environment') === 'test' ?
            Data::getResursOption('jwt_client_secret') : Data::getResursOption('jwt_client_secret_production');
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
     * @param $paymentId
     * @return mixed
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
     * @since 0.0.1.0
     * @todo Return data as ecom2 Payment object. Also look at the validation issues in ecom2 DataConverter.
     */
    public static function getPayment(string $paymentId): array
    {
        try {
            $return = PaymentRepository::get($paymentId);
        } catch (Exception $e) {
            Data::writeLogException($e, __FUNCTION__);

            // Only look for legacy payments if the initial get fails.
            $searchPayment = PaymentRepository::search(
                self::getStoreUuidByNationalId(Data::getStoreId()),
                $paymentId
            );

            if ($searchPayment->count() > 0) {
                /** @var Payment $currentSearchResult */
                $currentSearchResult = $searchPayment->current();

                $return = PaymentRepository::get(
                    $currentSearchResult->id
                )->toArray();
            } else {
                throw $e;
            }
        }

        return isset($return) && !empty($return) ? $return : new stdClass();
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
     */
    public static function getStoreCollection(): array
    {
        if (empty(self::$storeCollection)) {
            self::$storeCollection = StoreRepository::getStores();
        }

        return self::$storeCollection->toArray();
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
     * Fetch annuity factors from storage or new data.
     * @param $fromStorage
     * @return array|int|mixed|null
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection ParameterDefaultValueIsNotNullInspection
     */
    public static function getAnnuityFactors($fromStorage = true)
    {
        return [];
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
                if ($e->getCode() === 8) {
                    // Ignore this error, as it may be caused by wrong API.
                } elseif (is_object($stored)) {
                    WooCommerce::setSessionValue('silentAnnuityException', $e);
                    // If there are errors during this procedure, override the stored controller
                    // and return data if there is a cached storage. This makes it possible for the
                    // plugin to live without the access to Resurs Bank.
                    self::$annuityFactors = $stored;
                } else {
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
        Data::writeLogNotice(
            sprintf(
                __(
                    'Ecom request %s for %s with different credentials (%s, in environment %s).',
                    'resurs-bank-payments-for-woocommerce'
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
        Data::writeLogNotice(
            sprintf(
                __(
                    'Got exception %d in %s, will retry with REST.',
                    'resurs-bank-payments-for-woocommerce'
                ),
                $e->getCode(),
                __FUNCTION__
            )
        );
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

    /**
     * @return bool
     * @throws Exception
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
                Data::writeLogException($e, __FUNCTION__);
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


    ///////////////////// MAPI Based Features that is supposed to replace SOAP ///////////////////////

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
}
