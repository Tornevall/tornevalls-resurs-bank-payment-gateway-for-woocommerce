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
use Resursbank\Ecom\Lib\Model\Config\Network;
use Resursbank\Ecom\Lib\Model\Network\Auth\Jwt;
use Resursbank\Ecom\Module\Store\Repository;
use Resursbank\Woocommerce\Modules\UserSettings\Reader;
use Resursbank\Woocommerce\Modules\Cache\Transient;
use Resursbank\Woocommerce\Util\Admin;
use Resursbank\Woocommerce\Util\Currency;
use Resursbank\Woocommerce\Util\UserAgent;
use Resursbank\Woocommerce\Util\WooCommerce;
use Throwable;
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

            Config::setup(
                cache: new Transient(),
                isProduction: isset($jwt->scope) && $jwt->scope === Scope::MERCHANT_API,
                currencySymbol: Currency::getWooCommerceCurrencySymbol(),
                currencyFormat: Currency::getEcomCurrencyFormat(),
                network: new Network(
                    userAgent: UserAgent::getUserAgent()
                ),
                settingsReader: new Reader()
            );
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
}
