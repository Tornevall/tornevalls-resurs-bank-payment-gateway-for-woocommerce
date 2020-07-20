<?php

namespace ResursBank\Module;

use Exception;
use Resursbank\RBEcomPHP\RESURS_ENVIRONMENTS;
use Resursbank\RBEcomPHP\ResursBank;
use TorneLIB\Exception\ExceptionHandler;

/**
 * Class Api
 * @package ResursBank\Module
 * @since 0.0.1.0
 */
class Api
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
     * @param null $failover
     * @param null $orderInfo
     * @return mixed
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getPayment($orderId, $failover = null, $orderInfo = null)
    {
        try {
            if (($credentialMeta = self::getMatchingCredentials($orderInfo)) !== null) {
                $resurs = self::getTemporaryEcom($credentialMeta->l, $credentialMeta->p, $credentialMeta->e);
                Data::setLogNotice(
                    sprintf(
                        __(
                            'Ecom request %s for %s with different credentials (%s, in environment %s).',
                            'trbwc'
                        ),
                        __FUNCTION__,
                        $orderId,
                        $credentialMeta->l,
                        $credentialMeta->e
                    )
                );
                try {
                    if ((bool)$failover) {
                        $resurs->setFlag('GET_PAYMENT_BY_REST');
                    }
                    $return = $resurs->getPayment($orderId);
                    $return->isCurrentCredentials = false;
                    $return->username = $credentialMeta->l;
                    $return->environment = $credentialMeta->e;
                } catch (\Exception $e) {
                    Data::setLogException($e);
                    throw $e;
                }
            } else {
                try {
                    if ((bool)$failover) {
                        self::getResurs()->setFlag('GET_PAYMENT_BY_REST');
                    }
                    $return = self::getResurs()->getPayment($orderId);
                    $return->isCurrentCredentials = true;
                    $return->username = null;
                    $return->environment = null;
                } catch (\Exception $e) {
                    Data::setLogException($e);
                    throw $e;
                }
            }
        } catch (\Exception $e) {
            if (!(bool)$failover && $e->getCode() === 2) {
                Data::setLogNotice(
                    sprintf(
                        __(
                            'Got exception %d in %s, will retry with REST.',
                            'trbwc'
                        ),
                        $e->getCode(),
                        __FUNCTION__
                    )
                );
                return self::getPayment($orderId, true);
            }
            Data::setLogException($e);
            throw $e;
        }
        self::getResurs()->deleteFlag('GET_PAYMENT_BY_REST');

        return $return;
    }

    /**
     * Compare current credential setup with order meta credentials.
     * @param $orderInfo
     * @return bool
     * @throws ExceptionHandler
     * @since 0.0.1.0
     */
    private static function getMatchingCredentials($orderInfo)
    {
        $credentialMeta = @json_decode(self::getApiMeta($orderInfo));
        $return = null;

        $intersected = array_intersect(
            (array)$credentialMeta,
            [
                'l' => getResursOption('login'),
                'p' => getResursOption('password'),
                'e' => getResursOption('environment'),
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
     * @throws ExceptionHandler
     * @since 0.0.1.0
     */
    private static function getApiMeta($orderData)
    {
        return Data::getCrypt()->aesDecrypt(
            Data::getOrderMeta('orderapi', $orderData)
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
    private static function getTemporaryEcom($username, $password, $environment)
    {
        // Creating a simple connection.
        return new ResursBank(
            $credentialMeta->l,
            $credentialMeta->p,
            in_array($credentialMeta->e, ['test', 'staging']) ? 1 : 0
        );
    }

    /**
     * @return ResursBank
     * @throws Exception
     * @since 0.0.1.0
     */
    public static function getResurs()
    {
        if (empty(self::$resursBank)) {
            self::$resursBank = new Api();
        }

        return self::$resursBank->getConnection();
    }

    /**
     * @return ResursBank
     * @throws Exception
     * @since 0.0.1.0
     */
    public function getConnection()
    {
        if (empty($this->ecom)) {
            $this->getResolvedCredentials();
            $this->ecom = new ResursBank(
                $this->credentials['username'],
                $this->credentials['password'],
                Api::getEnvironment()
            );
            $this->setWsdlCache();
        }

        return $this->ecom;
    }

    /**
     * @throws Exception
     * @since 0.0.1.0
     */
    private function getResolvedCredentials()
    {
        $this->credentials['username'] = Data::getResursOption('login');
        $this->credentials['password'] = Data::getResursOption('password');

        if (empty($this->credentials['username']) || empty($this->credentials['password'])) {
            throw new Exception('ECom credentials are not fully set.', 404);
        }

        return true;
    }

    /**
     * @return bool
     * @since 0.0.1.0
     */
    public static function getEnvironment()
    {
        return in_array(
            Data::getResursOption('environment'),
            ['live', 'production'],
            true
        ) ?
            RESURS_ENVIRONMENTS::PRODUCTION : RESURS_ENVIRONMENTS::TEST;
    }

    /**
     * @return string
     * @throws Exception
     * @since 0.0.1.0
     */
    private function setWsdlCache()
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
        if ($fromStorage && is_array($stored = json_decode(Data::getResursOption('annuityFactors'), false))) {
            $return = $stored;
        }

        if (!$fromStorage || empty($return)) {
            try {
                $annuityArray = [];
                if (is_array($fromStorage)) {
                    $paymentMethods = $fromStorage;
                } else {
                    $paymentMethods = (array)Api::getPaymentMethods();
                }
                if (count($paymentMethods)) {
                    foreach ($paymentMethods as $paymentMethod) {
                        $annuityResponse = Api::getResurs()->getAnnuityFactors($paymentMethod->id);
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
                // Reset.
                Data::setResursOption('annuityFactors', null);
                throw $e;
            }
            $return = self::$annuityFactors;
            Data::setResursOption('annuityFactors', json_encode(self::$annuityFactors));
        }

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

        if ($fromStorage && is_array($stored = json_decode(Data::getResursOption('paymentMethods'), false))) {
            $return = $stored;
        }

        if (!$fromStorage || empty($return)) {
            try {
                self::$paymentMethods = Api::getResurs()->getPaymentMethods();
            } catch (Exception $e) {
                // Reset.
                Data::setResursOption('paymentMethods', null);
                throw $e;
            }
            $return = self::$paymentMethods;
            Data::setResursOption('paymentMethods', json_encode(self::$paymentMethods));
        }

        return $return;
    }

    /**
     * @return bool
     * @since 0.0.1.0
     */
    public function getCredentialsPresent()
    {
        $return = true;
        try {
            $this->getResolvedCredentials();
        } catch (Exception $e) {
            $return = false;
        }
        return $return;
    }
}
