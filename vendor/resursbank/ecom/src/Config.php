<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom;

use Resursbank\Ecom\Lib\Cache\CacheInterface;
use Resursbank\Ecom\Lib\Cache\None;
use Resursbank\Ecom\Lib\Locale\Locale;
use Resursbank\Ecom\Lib\Log\LoggerInterface;
use Resursbank\Ecom\Lib\Log\LogLevel;
use Resursbank\Ecom\Lib\Network\Model\Auth\Basic;
use Resursbank\Ecom\Lib\Network\Model\Auth\Jwt;

/**
 * API communication object.
 */
final class Config
{
    public static Config $instance;

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
     */
    public static function setup(
        LoggerInterface $logger,
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
}
