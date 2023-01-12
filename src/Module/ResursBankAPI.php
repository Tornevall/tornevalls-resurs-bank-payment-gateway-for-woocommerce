<?php

namespace ResursBank\Module;

use Exception;
use JsonException;
use Locale;
use ReflectionException;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\CacheException;
use Resursbank\Ecom\Exception\CollectionException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Locale\Language;
use Resursbank\Ecom\Lib\Model\Network\Auth\Jwt;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Module\Payment\Repository as PaymentRepository;
use Resursbank\Ecom\Module\PaymentMethod\Repository;
use ResursBank\Exception\MapiCredentialsException;
use Resursbank\RBEcomPHP\RESURS_ENVIRONMENTS;
use Resursbank\RBEcomPHP\ResursBank;
use ResursBank\Service\WooCommerce;
use ResursBank\Service\WordPress;
use Resursbank\Woocommerce\Database\Options\ClientId;
use Resursbank\Woocommerce\Database\Options\ClientSecret;
use Resursbank\Woocommerce\Database\Options\Environment;
use Resursbank\Woocommerce\Database\Options\StoreId;
use Resursbank\Woocommerce\Settings\Advanced;
use ResursException;
use stdClass;

/**
 * Class Api EComPHP Translator. Also a guide for what could be made better in the real API.
 * @package ResursBank\Module
 * @since 0.0.1.0
 */
class ResursBankAPI
{
    /** @var Language */
    private const DEFAULT_LANGUAGE = Language::en;

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
     * @throws MapiCredentialsException
     * @throws Exception
     * @since 0.0.1.0
     */
    public function getConnection(): void
    {
        if (!Config::hasInstance()) {
            return;
        }

        if (empty(ClientId::getData()) || empty(ClientSecret::getData())) {
            throw new MapiCredentialsException(
                message: 'Credentials not set.'
            );
        }

        WC()->initialize_session();

        // @todo Loglevel error removed and points to the default (info) for now as it is used in the callback handler
        // @todo for the moment. This should instead be configurable.
        Config::setup(
            logger: Advanced::getLogger(),
            cache: Advanced::getCache(),
            jwtAuth: new Jwt(
                clientId: ClientId::getData(),
                clientSecret: ClientSecret::getData(),
                scope: Environment::getData() === 'test' ? 'mock-merchant-api' : 'merchant-api',
                grantType: 'client_credentials'
            ),
            language: $this->getSiteLanguage()
        );
    }

    /**
     * Get payment properly by testing two API's before giving up.
     * @param string $paymentId
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
     * @throws ValidationException
     * @throws CollectionException
     * @throws ConfigException
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
                StoreId::getData(),
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
            self::$paymentMethods = Repository::getPaymentMethods(Data::getStoreId())->toArray();
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
     * @todo Can probably be removed. Make sure it's done safely, when switching code to MAPI.
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
     * Attempts to somewhat safely fetch the correct site language.
     *
     * @return Language Configured language or self::DEFAULT_LANGUAGE if no matching language found in Ecom
     */
    private function getSiteLanguage(): Language
    {
        $language = Locale::getPrimaryLanguage(locale: get_locale());

        if (!$language) {
            return self::DEFAULT_LANGUAGE;
        }

        foreach (Language::cases() as $case) {
            if ($language === $case->value) {
                return $case;
            }
        }

        return self::DEFAULT_LANGUAGE;
    }
}
