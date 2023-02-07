<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Util;

use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Http\Controller as CoreController;
use Resursbank\Woocommerce\Modules\Cache\Controller\Admin\Invalidate;
use Resursbank\Woocommerce\Modules\CustomerType\Controller\SetCustomerType;
use Resursbank\Woocommerce\Modules\GetAddress\Controller\GetAddress;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Modules\PartPayment\Controller\Admin\GetValidDurations;
use Resursbank\Woocommerce\Modules\PartPayment\Controller\PartPayment;
use Resursbank\Woocommerce\Settings\Advanced;
use Throwable;

use function is_string;
use function str_contains;
use function strlen;

/**
 * Primitive routing, executing arbitrary code depending on $_GET parameters.
 */
class Route
{
    /**
     * Name of the $_GET parameter containing the routing name.
     */
    public const ROUTE_PARAM = 'resursbank';

    /**
     * Route to get address controller.
     */
    public const ROUTE_GET_ADDRESS = 'get-address';

    /**
     * Route to update current customer type in session.
     */
    public const ROUTE_SET_CUSTOMER_TYPE = 'set-customer-type';

    /**
     * Route to get part payment controller.
     */
    public const ROUTE_PART_PAYMENT = 'part-payment';

    /**
     * Route to get part payment admin controller.
     */
    public const ROUTE_PART_PAYMENT_ADMIN = 'part-payment-admin';

    /**
     * Route to get part payment admin controller.
     */
    public const ROUTE_ADMIN_CACHE_INVALIDATE = 'admin-cache-invalidate';

    /**
     * @SuppressWarnings(PHPMD.Superglobals)
     * @SuppressWarnings(PHPMD.ExitExpression)
     */
    public static function exec(): void
    {
        $route = (
            isset($_GET[self::ROUTE_PARAM]) &&
            is_string(value: $_GET[self::ROUTE_PARAM])
        ) ? $_GET[self::ROUTE_PARAM] : '';

        try {
            switch ($route) {
                case self::ROUTE_GET_ADDRESS:
                    self::respondWithExit(body: GetAddress::exec());
                    break;

                case self::ROUTE_PART_PAYMENT:
                    self::respondWithExit(body: PartPayment::exec());
                    break;

                case self::ROUTE_PART_PAYMENT_ADMIN:
                    self::respondWithExit(body: GetValidDurations::exec());
                    break;

                case self::ROUTE_SET_CUSTOMER_TYPE:
                    self::respondWithExit(body: SetCustomerType::exec());
                    exit;

                case self::ROUTE_ADMIN_CACHE_INVALIDATE:
                    Invalidate::exec();
                    self::redirectToSettings(tab: Advanced::SECTION_ID);
                    break;

                default:
                    break;
            }
        } catch (Throwable $exception) {
            self::respondWithError(exception: $exception);
        }
    }

    /**
     * Redirect request to WC Settings configuration tab for our plugin.
     */
    public static function redirectToSettings(
        string $tab = 'api_settings'
    ): void {
        wp_safe_redirect(
            location: admin_url(
                path: 'admin.php?page=wc-settings&tab='
                    . RESURSBANK_MODULE_PREFIX
                    . "&section=$tab"
            )
        );

        MessageBag::keep();
    }

    /**
     * Resolve full URL.
     *
     * @throws HttpException|IllegalValueException
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     */
    public static function getUrl(
        string $route,
        bool $admin = false
    ): string {
        $url = !$admin ? get_site_url() : get_admin_url();

        if (!is_string(value: $url)) {
            throw new HttpException(
                message: 'A site URL could not be created.'
            );
        }

        // Some sites may not add the trailing slash properly, making urls break with arguments
        // merged into the hostname instead of the uri. This one fixes that problem.
        $url = self::getUrlWithProperTrailingSlash(url: $url);
        $url .= str_contains(haystack: $url, needle: '?') ? '&' : '?';

        return Url::getQueryArg(
            baseUrl: $url,
            arguments: [self::ROUTE_PARAM => $route]
        );
    }

    /**
     * Echo JSON response.
     */
    public static function respond(
        string $body,
        int $code = 200
    ): void {
        header(header: 'Content-Type: application/json');
        header(header: 'Content-Length: ' . strlen(string: $body));
        http_response_code(response_code: $code);

        echo $body;
    }

    /**
     * Method that exits after response instead of proceeding with regular WordPress executions.
     *
     * In some cases, during API responding, WordPress could potentially execute other data that renders
     * more content after the final json responses, and breaks the requests. This happens due to how
     * WP is handling unknown requests and depends on how the site is configured with permalinks and rewrite-urls.
     * For example, when WP handles 404 errors on unknown http-requests, we have to stop our own execution
     * like this.
     *
     * @SuppressWarnings(PHPMD.ExitExpression)
     * @noinspection PhpNoReturnAttributeCanBeAddedInspection
     */
    public static function respondWithExit(
        string $body,
        int $code = 200
    ): void {
        self::respond(body: $body, code: $code);
        exit;
    }

    /**
     * Respond to browser with an error based on Throwable.
     */
    public static function respondWithError(
        Throwable $exception
    ): void {
        $controller = new CoreController();

        self::respond(
            body: $controller->respondWithError(
                exception: new HttpException(
                    message: $exception->getMessage(),
                    previous: $exception
                )
            ),
            code: $controller->getErrorResponseCode(
                exception: new HttpException(
                    message: $exception->getMessage(),
                    previous: $exception
                )
            )
        );
    }

    /**
     * Fix trailing slashes for urls that is missing them out.
     */
    private static function getUrlWithProperTrailingSlash(string $url): string
    {
        return preg_replace(
            pattern: '/\/$/',
            replacement: '',
            subject: $url
        ) . '/';
    }
}
