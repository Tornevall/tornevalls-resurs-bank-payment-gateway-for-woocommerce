<?php

namespace ResursBank\Module;

use Exception;
use Resursbank\RBEcomPHP\RESURS_ENVIRONMENTS;
use Resursbank\RBEcomPHP\ResursBank;

/**
 * Class Api
 * @package ResursBank\Module
 * @since 0.0.1.0
 */
class Api
{
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
     */
    public static function getWsdlMode()
    {
        return Data::getResursOption('api_wsdl');
    }

    /**
     * @return bool
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
     * @return ResursBank
     * @throws Exception
     * @since 0.0.1.0
     */
    public function getConnection()
    {
        $this->getResolvedCredentials();
        $this->ecom = new ResursBank(
            $this->credentials['username'],
            $this->credentials['password'],
            Api::getEnvironment()
        );
        $this->setWsdlCache();

        return $this->ecom;
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
}