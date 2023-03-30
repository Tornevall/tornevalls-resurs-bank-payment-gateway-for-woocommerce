<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Api;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\AuthException;
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
use Resursbank\Woocommerce\Database\Options\Advanced\EnableCache;
use Resursbank\Woocommerce\Database\Options\Advanced\LogDir;
use Resursbank\Woocommerce\Database\Options\Advanced\LogLevel;
use Resursbank\Woocommerce\Database\Options\Api\ClientId;
use Resursbank\Woocommerce\Database\Options\Api\ClientSecret;
use Resursbank\Woocommerce\Database\Options\Api\Environment;
use Resursbank\Woocommerce\Modules\Cache\Transient;
use Resursbank\Woocommerce\Util\Language;
use Resursbank\Woocommerce\Util\UserAgent;
use Throwable;
use ValueError;
use WC_Logger;

use function function_exists;

/**
 * API connection adapter.
 *
 * @noinspection EfferentObjectCouplingInspection
 */
class Connection
{
    /**
     * Setup ECom API connection (creates a singleton to handle API calls).
     */
    public static function setup(
        ?Jwt $jwt = null
    ): void {
        try {
            if (function_exists(function: 'WC')) {
                WC()->initialize_session();
            }

            if ($jwt === null && self::hasCredentials()) {
                $jwt = self::getConfigJwt();
            }

            Config::setup(
                logger: self::getLogger(),
                cache: self::getCache(),
                logLevel: LogLevel::getData(),
                jwtAuth: $jwt,
                language: Language::getSiteLanguage(),
                userAgent: UserAgent::getUserAgent()
            );
        } catch (Throwable) {
            // Nothing we can do.
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
     * @throws AuthException
     * @throws EmptyValueException
     * @throws ValueError
     */
    public static function getConfigJwt(): ?Jwt
    {
        if (!self::hasCredentials()) {
            throw new AuthException(message: 'Credentials are not set.');
        }

        return new Jwt(
            clientId: ClientId::getData(),
            clientSecret: ClientSecret::getData(),
            scope: Environment::getData() === EnvironmentEnum::PROD ?
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
     * Resolve cache interface.
     */
    public static function getCache(): CacheInterface
    {
        return EnableCache::isEnabled() ? new Transient() : new None();
    }
}
