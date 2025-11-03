<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Api;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Lib\Api\Scope;
use Resursbank\Ecom\Lib\Model\Config\Network;
use Resursbank\Ecom\Module\PaymentMethod\Enum\CurrencyFormat;
use Resursbank\Woocommerce\Modules\Order\PaymentHistory;
use Resursbank\Woocommerce\Modules\UserSettings\Reader;
use Resursbank\Woocommerce\Modules\Cache\Transient;
use Resursbank\Woocommerce\Util\Admin;
use Resursbank\Woocommerce\Util\UserAgent;
use Throwable;

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
    public static function setup(): void
    {
        try {
            if (function_exists(function: 'WC')) {
                WC()->initialize_session();
            }

            Config::setup(
                cache: new Transient(),
                network: new Network(
                    userAgent: UserAgent::getUserAgent()
                ),
                settingsReader: new Reader(),
                paymentHistoryDataHandler: new PaymentHistory()
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
}
