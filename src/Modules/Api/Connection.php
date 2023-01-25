<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Api;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Lib\Api\Environment as EnvironmentEnum;
use Resursbank\Ecom\Lib\Api\GrantType;
use Resursbank\Ecom\Lib\Api\Scope;
use Resursbank\Ecom\Lib\Model\Network\Auth\Jwt;
use ResursBank\Exception\MapiCredentialsException;
use Resursbank\Woocommerce\Database\Options\ClientId;
use Resursbank\Woocommerce\Database\Options\ClientSecret;
use Resursbank\Woocommerce\Database\Options\Environment;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Settings\Advanced;
use Resursbank\Woocommerce\Util\Language;
use Throwable;

use function function_exists;

/**
 * API connection adapter.
 */
class Connection
{
    /**
     * Setup ECom API connection (creates a singleton to handle API calls).
     *
     * @throws ConfigException
     */
    public static function setup(): void
    {
        try {
            if (function_exists(function: 'WC')) {
                WC()->initialize_session();
            }

            Config::setup(
                logger: Advanced::getLogger(),
                cache: Advanced::getCache(),
                logLevel: Advanced::getLogLevel(),
                jwtAuth: self::hasCredentials() ? self::getJwt() : null,
                language: Language::getSiteLanguage()
            );
        } catch (Throwable $e) {
            MessageBag::addError(msg: 'Failed to initiate ECom library.');
            Config::getLogger()->error(message: $e);
        }
    }

    /**
     * Ensure we have available credentials.
     */
    public static function hasCredentials(): bool
    {
        $clientId = ClientId::getData();
        $clientSecret = ClientSecret::getData();

        return $clientId !== '' && $clientSecret !== '';
    }

    /**
     * @throws MapiCredentialsException
     * @throws EmptyValueException
     */
    public static function getJwt(): ?Jwt
    {
        if (!self::hasCredentials()) {
            throw new MapiCredentialsException(
                message: 'Credentials are not set.'
            );
        }

        return new Jwt(
            clientId: ClientId::getData(),
            clientSecret: ClientSecret::getData(),
            scope: Environment::getData() === EnvironmentEnum::PROD->value ?
                Scope::MERCHANT_API :
                Scope::MOCK_MERCHANT_API,
            grantType: GrantType::CREDENTIALS
        );
    }
}
