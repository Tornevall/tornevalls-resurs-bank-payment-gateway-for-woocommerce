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
use Resursbank\Woocommerce\Modules\GetAddress\Controller\GetAddress;
use Resursbank\Woocommerce\Modules\PartPayment\Controller\Admin\GetValidDurations;
use Resursbank\Woocommerce\Modules\PartPayment\Controller\PartPayment;
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
     * Route to get part payment controller.
     */
    public const ROUTE_PART_PAYMENT = 'part-payment';

    /**
     * Route to get part payment admin controller.
     */
    public const ROUTE_PART_PAYMENT_ADMIN = 'part-payment-admin';

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

                default:
                    break;
            }
        } catch (Throwable $exception) {
            self::respondWithError(exception: $exception);
        }
    }

    /**
     * Resolve full URL.
     *
     * @throws HttpException|IllegalValueException
     */
    public static function getUrl(
        string $route
    ): string {
        $url = get_site_url();

        if (!is_string(value: $url)) {
            throw new HttpException(
                message: 'A site URL could not be created.'
            );
        }

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
}
