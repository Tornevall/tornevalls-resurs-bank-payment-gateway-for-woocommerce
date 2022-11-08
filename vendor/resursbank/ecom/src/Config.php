<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom;

use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Lib\Cache\CacheInterface;
use Resursbank\Ecom\Lib\Cache\None;
use Resursbank\Ecom\Lib\Locale\Locale;
use Resursbank\Ecom\Lib\Log\LoggerInterface;
use Resursbank\Ecom\Lib\Log\LogLevel;
use Resursbank\Ecom\Lib\Log\NoneLogger;
use Resursbank\Ecom\Lib\Model\Network\Auth\Basic;
use Resursbank\Ecom\Lib\Model\Network\Auth\Jwt;

/**
 * API communication object.
 *
 * @noinspection PhpClassHasTooManyDeclaredMembersInspection
 * @SuppressWarnings(PHPMD.ExcessiveParameterList)
 * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
 */
final class Config
{
    /**
     * NOTE: This is a singleton class. Use Config::setup() to generate an
     * instance, use getter methods to extract properties safely.
     *
     * NOTE: Nullable to allow unsetting configuration.
     */
    private static ?Config $instance;

    /**
     * @param LoggerInterface $logger
     * @param CacheInterface $cache
     * @param Basic|null $basicAuth
     * @param Jwt|null $jwtAuth
     * @param LogLevel $logLevel
     * @param string $userAgent
     * @param bool $isProduction
     * @param string $proxy
     * @param int $proxyType
     * @param int $timeout
     * @param Locale $locale
     * @todo Create a null cache driver, so there always is one, returns null always
     * @todo Create a null database driver, so there always is one, returns null always
     */
    public function __construct(
        public readonly LoggerInterface $logger,
        public readonly CacheInterface $cache,
        public readonly Basic|null $basicAuth,
        public readonly Jwt|null $jwtAuth,
        public readonly LogLevel $logLevel = LogLevel::INFO,   // Only log info messages.
        public readonly string $userAgent = '',
        public readonly bool $isProduction = false,
        public readonly string $proxy = '',
        public readonly int $proxyType = 0,
        public readonly int $timeout = 60,
        public readonly Locale $locale = Locale::en,
    ) {
    }

    /**
     * @param LoggerInterface $logger
     * @param CacheInterface $cache
     * @param Basic|null $basicAuth
     * @param Jwt|null $jwtAuth
     * @param LogLevel $logLevel
     * @param string $userAgent
     * @param bool $isProduction
     * @param string $proxy
     * @param int $proxyType
     * @param int $timeout
     * @param Locale $locale
     * @return void
     * @noinspection PhpTooManyParametersInspection
     * @todo Consider making userAgent an object instead.
     * @todo Consider moving proxy, proxyType and timeout to a separate object.
     */
    public static function setup(
        LoggerInterface $logger = new NoneLogger(),
        CacheInterface $cache = new None(),
        Basic|null $basicAuth = null,
        Jwt|null $jwtAuth = null,
        LogLevel $logLevel = LogLevel::INFO,   // Only log info messages.
        string $userAgent = '',
        bool $isProduction = false,
        string $proxy = '',
        int $proxyType = 0,
        int $timeout = 0,
        Locale $locale = Locale::en,
    ): void {
        self::$instance = new Config(
            logger: $logger,
            cache: $cache,
            basicAuth: $basicAuth,
            jwtAuth: $jwtAuth,
            logLevel: $logLevel,
            userAgent: $userAgent,
            isProduction: $isProduction,
            proxy: $proxy,
            proxyType: $proxyType,
            timeout: $timeout,
            locale: $locale
        );
    }

    /**
     * Checks if Basic auth is configured
     *
     * @return bool
     */
    public static function hasBasicAuth(): bool
    {
        return isset(self::$instance->basicAuth);
    }

    /**
     * Checks if JWT auth is configured
     *
     * @return bool
     */
    public static function hasJwtAuth(): bool
    {
        return isset(self::$instance->jwtAuth);
    }

    /**
     * Checks if there is a Config instance
     *
     * @return bool
     */
    public static function hasInstance(): bool
    {
        return isset(self::$instance);
    }

    /**
     * Clears active configuration
     *
     * @return void
     */
    public static function unsetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * @return void
     * @throws ConfigException
     */
    public static function validateInstance(): void
    {
        if (self::$instance === null) {
            throw new ConfigException(
                message: 'Config instance not set. Please run Config::setup()'
            );
        }
    }

    /**
     * @return LoggerInterface
     * @throws ConfigException
     */
    public static function getLogger(): LoggerInterface
    {
        self::validateInstance();
        return self::$instance->logger;
    }

    /**
     * @return CacheInterface
     * @throws ConfigException
     */
    public static function getCache(): CacheInterface
    {
        self::validateInstance();
        return self::$instance->cache;
    }

    /**
     * @return Basic|null
     * @throws ConfigException
     */
    public static function getBasicAuth(): Basic|null
    {
        self::validateInstance();
        return self::$instance->basicAuth;
    }

    /**
     * @return Jwt|null
     * @throws ConfigException
     */
    public static function getJwtAuth(): Jwt|null
    {
        self::validateInstance();
        return self::$instance->jwtAuth;
    }

    /**
     * @return LogLevel
     * @throws ConfigException
     */
    public static function getLogLevel(): LogLevel
    {
        self::validateInstance();
        return self::$instance->logLevel;
    }

    /**
     * @return string
     * @throws ConfigException
     */
    public static function getUserAgent(): string
    {
        self::validateInstance();
        return self::$instance->userAgent;
    }

    /**
     * @return bool
     * @throws ConfigException
     */
    public static function isProduction(): bool
    {
        self::validateInstance();
        return self::$instance->isProduction;
    }

    /**
     * @return string
     * @throws ConfigException
     */
    public static function getProxy(): string
    {
        self::validateInstance();
        return self::$instance->proxy;
    }

    /**
     * @return int
     * @throws ConfigException
     */
    public static function getProxyType(): int
    {
        self::validateInstance();
        return self::$instance->proxyType;
    }

    /**
     * @return int
     * @throws ConfigException
     */
    public static function getTimeout(): int
    {
        self::validateInstance();
        return self::$instance->timeout;
    }

    /**
     * @return Locale
     * @throws ConfigException
     */
    public static function getLocale(): Locale
    {
        self::validateInstance();
        return self::$instance->locale;
    }
}
