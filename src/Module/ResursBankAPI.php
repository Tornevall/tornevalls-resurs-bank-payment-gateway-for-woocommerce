<?php

namespace ResursBank\Module;

use Exception;
use Locale;
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
    /** @var Language */
    private const DEFAULT_LANGUAGE = Language::en;


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
