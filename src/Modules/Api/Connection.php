<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Api;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Lib\Api\Environment as EnvironmentEnum;
use Resursbank\Ecom\Lib\Api\GrantType;
use Resursbank\Ecom\Lib\Api\Scope;
use Resursbank\Ecom\Lib\Cache\CacheInterface;
use Resursbank\Ecom\Lib\Cache\None;
use Resursbank\Ecom\Lib\Log\FileLogger;
use Resursbank\Ecom\Lib\Log\LoggerInterface;
use Resursbank\Ecom\Lib\Log\NoneLogger;
use Resursbank\Ecom\Lib\Model\Network\Auth\Jwt;
use ResursBank\Exception\MapiCredentialsException;
use ResursBank\Service\WordPress;
use Resursbank\Woocommerce\Database\Options\Advanced\CacheEnabled;
use Resursbank\Woocommerce\Database\Options\ClientId;
use Resursbank\Woocommerce\Database\Options\ClientSecret;
use Resursbank\Woocommerce\Database\Options\Environment;
use Resursbank\Woocommerce\Database\Options\LogDir;
use Resursbank\Woocommerce\Database\Options\LogLevel;
use Resursbank\Woocommerce\Modules\Cache\Transient;
use Resursbank\Woocommerce\Util\Language;
use Throwable;
use WC_Logger;

use function function_exists;

/**
 * API connection adapter.
 */
class Connection
{
    /**
     * Setup ECom API connection (creates a singleton to handle API calls).
     */
    public static function setup(): void
    {
        try {
            if (function_exists(function: 'WC')) {
                WC()->initialize_session();
            }

            Config::setup(
                logger: self::getLogger(),
                cache: self::getCache(),
                logLevel: LogLevel::getLogLevel(),
                jwtAuth: self::hasCredentials() ? self::getJwt() : null,
                language: Language::getSiteLanguage()
            );
        } catch (Throwable $e) {
            if (is_admin()) {
                // Display friendly error message inside admin panel.
                WordPress::setGenericError(exception: $e);
            }
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

    /**
     * Resolve log handler based on supplied setting value. Returns a dummy
     * if the setting is empty.
     */
    public static function getLogger(): LoggerInterface
    {
        $result = new NoneLogger();

        try {
            $result = new FileLogger(path: LogDir::getData());
        } catch (Throwable $e) {
            if (class_exists(class: WC_Logger::class)) {
                (new WC_Logger())->critical(
                    message: 'Resurs Bank: ' . $e->getMessage()
                );
            }
        }

        return $result;
    }

    /**
     * @return CacheInterface
     */
    public static function getCache(): CacheInterface
    {
        return CacheEnabled::isEnabled() ? new Transient() : new None();
    }
}
