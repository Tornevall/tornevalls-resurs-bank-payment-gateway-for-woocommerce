<?php

namespace ResursBank\Module;

use Exception;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Lib\Locale\Language;
use Resursbank\Ecom\Lib\Model\Network\Auth\Jwt;
use ResursBank\Exception\MapiCredentialsException;
use ResursBank\Service\WordPress;
use Resursbank\Woocommerce\Database\Options\ClientId;
use Resursbank\Woocommerce\Database\Options\ClientSecret;
use Resursbank\Woocommerce\Database\Options\Environment;
use Resursbank\Woocommerce\Settings\Advanced;

/**
 * Class Api EComPHP Translator. Also a guide for what could be made better in the real API.
 * @package ResursBank\Module
 * @since 0.0.1.0
 */
class ResursBankAPI
{
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
                WordPress::setGenericError(exception: $connectionException);
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

        Config::setup(
            logger: Advanced::getLogger(),
            cache: Advanced::getCache(),
            logLevel: Advanced::getLogLevel(),
            jwtAuth: new Jwt(
                clientId: ClientId::getData(),
                clientSecret: ClientSecret::getData(),
                scope: Environment::getData() === 'test' ? 'mock-merchant-api' : 'merchant-api',
                grantType: 'client_credentials'
            ),
            language: Language::getSiteLanguage()
        );
    }
}
