<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Api;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\AttributeCombinationException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Lib\Api\Environment as EnvironmentEnum;
use Resursbank\Ecom\Lib\Api\GrantType;
use Resursbank\Ecom\Lib\Cache\CacheInterface;
use Resursbank\Ecom\Lib\Cache\None;
use Resursbank\Ecom\Lib\Log\FileLogger;
use Resursbank\Ecom\Lib\Log\LoggerInterface;
use Resursbank\Ecom\Lib\Log\NoneLogger;
use Resursbank\Ecom\Lib\Model\Config\Network;
use Resursbank\Ecom\Lib\Model\Network\Auth\Jwt;
use Resursbank\Ecom\Module\Store\Repository;
use Resursbank\Woocommerce\Database\Options\Advanced\ApiTimeout;
use Resursbank\Woocommerce\Database\Options\Advanced\EnableCache;
use Resursbank\Woocommerce\Database\Options\Advanced\LogDir;
use Resursbank\Woocommerce\Database\Options\Advanced\LogEnabled;
use Resursbank\Woocommerce\Database\Options\Advanced\LogLevel;
use Resursbank\Woocommerce\Database\Options\Advanced\StoreId;
use Resursbank\Woocommerce\Database\Options\Api\ClientId;
use Resursbank\Woocommerce\Database\Options\Api\ClientSecret;
use Resursbank\Woocommerce\Database\Options\Api\Environment;
use Resursbank\Woocommerce\Modules\Cache\Transient;
use Resursbank\Woocommerce\Util\Admin;
use Resursbank\Woocommerce\Util\Currency;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\UserAgent;
use Resursbank\Woocommerce\Util\WooCommerce;
use Resursbank\Woocommerce\Util\WordPress;
use Throwable;
use WC_Logger;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * API connection adapter.
 *
 * @noinspection EfferentObjectCouplingInspection
 */
class Connection
{
    /**
     * Setup ECom API connection (creates a singleton to handle API calls).
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.EmptyCatchBlock)
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    // phpcs:ignore
    public static function setup(
        ?Jwt $jwt = null
    ): void {
        try {
            if (function_exists(function: 'WC')) {
                WC()->initialize_session();
            }

            // Marks the current JWT if it is used from _POST-vars or if it is used from a stored setup.
            // Conditions are that data is saved from wp-admin under very specific circumstances.
            $hasPostJwtInstance = false;

            $isProduction = Environment::getData() === EnvironmentEnum::PROD;
            if ($jwt === null && self::getJwtFromPost() instanceof Jwt) {
                // In the wc-save-section, options are only allowed to be saved if they are present in the options list.
                // If we can't fetch credentials in an early "save" we can't generate a new store list properly.
                $jwt = self::getJwtFromPost();
                $hasPostJwtInstance = $jwt instanceof Jwt;
                $isProduction = WordPress::getEnvironmentFromAdminAjax() === 'production';
            } else if ($jwt === null && self::hasCredentials()) {
                $jwt = self::getConfigJwt();
            }

            $timeout = (int)ApiTimeout::getData();

            if ($timeout <= 0) {
                // Default to curl defaults if negative or 0.
                $timeout = 30;
            }

            // For internal usages (dashboard).
            $useProxy = apply_filters('resursbank_mapi_proxy', '') ?? '';

            if (!is_string($useProxy)) {
                $useProxy = '';
            }

            Config::setup(
                logger: self::getLogger(),
                cache: self::getCache(),
                jwtAuth: $jwt,
                logLevel: LogLevel::getData(),
                isProduction: $isProduction,
                currencySymbol: Currency::getWooCommerceCurrencySymbol(),
                currencyFormat: Currency::getEcomCurrencyFormat(),
                network: new Network(
                    timeout: $timeout,
                    userAgent: UserAgent::getUserAgent(),
                    proxy: $useProxy
                ),
                storeId: StoreId::getData()
            );

            if ($hasPostJwtInstance) {
                try {
                    // We need to clear store list cache after ecom init, but before the getStores-request.
                    // This is a requirement since the list of stores may be cached at this point.
                    Repository::getCache()->clear();
                    WooCommerce::invalidateFullCache();
                } catch (Throwable) {
                }
            }
        } catch (Throwable $e) {
            // We are unable to use loggers here (neither WC_Logger nor ecom will be available in this state).
            // If admin_notices are available we can however at least display such errors.
            if (Admin::isAdmin()) {
                add_action('admin_notices', static function () use ($e): void {
                    // As we're eventually also catching other errors at this point, we will also show a stack trace
                    // for those who sees it as file loggers may miss it.
                    echo wp_kses(
                        '<div class="notice notice-error"><p>Resurs Bank Error: ' . $e->getMessage() .
                        ' (<pre>' . $e->getTraceAsString() . '</pre>)' . '</p></div>',
                        ['div' => ['class' => true]]
                    );
                });
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
     * @throws AuthException
     * @throws JsonException
     * @throws ReflectionException
     * @throws AttributeCombinationException
     */
    public static function getConfigJwt(): ?Jwt
    {
        if (!self::hasCredentials()) {
            throw new AuthException(message: 'Credentials are not set.');
        }

        return new Jwt(
            clientId: ClientId::getData(),
            clientSecret: ClientSecret::getData(),
            grantType: GrantType::CREDENTIALS,
            cacheToken: true
        );
    }

    /**
     * Resolve log handler based on supplied setting value. Returns a dummy
     * if the setting is empty.
     */
    public static function getLogger(): LoggerInterface
    {
        $result = new NoneLogger();

        if (!LogEnabled::getData() || LogDir::getData() === '') {
            return $result;
        }

        try {
            $result = new FileLogger(path: LogDir::getData());
        } catch (Throwable $e) {
            if (class_exists(class: WC_Logger::class)) {
                self::getWcLoggerCritical(
                    message: 'Resurs Bank: ' . $e->getMessage()
                );
            }
        }

        return $result;
    }

    /**
     * Make sure we only log our messages if WP/WC allows it.
     *
     * @SuppressWarnings(PHPMD.EmptyCatchBlock)
     */
    public static function getWcLoggerCritical(string $message): void
    {
        try {
            // If WordPress/WooCommerce cannot handle their own logging errors when we attempt to log critical
            // messages, we suppress them here.
            //
            // We've observed this issue with PHP 8.3 and errors that occurs in `class-wp-filesystem-ftpext.php`
            // for where errors are only shown on screen and never logged. Improved error handling reveals previously
            // unnoticed logging issues may be the problem.
            (new WC_Logger())->critical(message: $message);
        } catch (Throwable) {
        }
    }

    /**
     * Resolve cache interface.
     */
    public static function getCache(): CacheInterface
    {
        return EnableCache::isEnabled() ? new Transient() : new None();
    }

    /**
     * Get JWT from $_POST. Used on early update_option requests from where we need to try to fetch store lists
     * with not-yet-set credentials.
     *
     * Nonce verification is not performed here because:
     * 1. This is a private method, only called internally after Admin::isAdmin() + Admin::isTab() checks
     * 2. WooCommerce Settings API handles nonce verification internally for all settings forms
     * 3. This method extracts data from the WooCommerce-validated POST payload during settings save
     * 4. Adding duplicate nonce verification would fail as WC uses its own nonce actions
     *
     * @throws AttributeCombinationException
     * @throws JsonException
     * @throws ReflectionException
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    // phpcs:ignore
    private static function getJwtFromPost(): ?Jwt
    {
        // WordPress usually delivers nonces for us here, but we can't use it to verify the nonce in this early state
        // since WP is not guaranteed to be present. However, we can verify that users are admins and that the
        // usual request variables for updating options are present. This access request must be limited to one section
        // only.

        $route = WordPress::getQueryParam(key: Route::ROUTE_PARAM);
        $isStoresAdminRoute = $route === Route::ROUTE_GET_STORES_ADMIN;

        if ($isStoresAdminRoute) {
            WordPress::ensurePluggableLoaded();

            if (function_exists('current_user_can') && !current_user_can('manage_woocommerce')) {
                return null;
            }

            // Get cached JSON payload (read once, reused across the request)
            $payload = WordPress::getJsonPayload();

            // Verify nonce from JSON payload or query string
            $jsonNonceOk = WordPress::verifyJsonNonce(
                payload: $payload,
                action: 'resursbank_get_stores_admin',
                field: 'nonce'
            );

            $queryNonce = WordPress::getQueryParam('_wpnonce');
            $queryNonceOk = $queryNonce !== '' && WordPress::verifyNonce(
                nonce: $queryNonce,
                action: 'resursbank_get_stores_admin'
            );

            if (!$jsonNonceOk && !$queryNonceOk) {
                return null;
            }

            // Extract credentials from JSON payload
            $clientId = WordPress::getJsonParam('clientId') ?? '';
            $clientSecret = WordPress::getJsonParam('clientSecret') ?? '';
            $environment = WordPress::getJsonParam('environment') ?? '';

            if ($clientId === '' || $clientSecret === '' || $environment === '') {
                return null;
            }

            return new Jwt(
                clientId: $clientId,
                clientSecret: $clientSecret,
                grantType: GrantType::CREDENTIALS
            );
        } elseif (
            !Admin::isAdmin() ||
            !Admin::isTab(tabName: RESURSBANK_MODULE_PREFIX)
        ) {
            return null;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce Settings API handles nonce verification
        $clientId = WordPress::getPostParam(
            key: RESURSBANK_MODULE_PREFIX . '_client_id'
        );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce Settings API handles nonce verification
        $clientSecret = WordPress::getPostParam(
            key: RESURSBANK_MODULE_PREFIX . '_client_secret'
        );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce Settings API handles nonce verification
        $environment = WordPress::getPostParam(
            key: RESURSBANK_MODULE_PREFIX . '_environment'
        );

        if ($clientId === '' || $clientSecret === '' || $environment === '') {
            return null;
        }

        return new Jwt(
            clientId: $clientId,
            clientSecret: $clientSecret,
            grantType: GrantType::CREDENTIALS
        );
    }
}
