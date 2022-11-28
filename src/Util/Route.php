<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare( strict_types=1 );

namespace Resursbank\Woocommerce\Util;

use Exception;
use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Lib\Http\Controller;
use Resursbank\Ecom\Lib\Http\Controller as CoreController;
use Resursbank\Woocommerce\Modules\GetAddress\Controller\GetAddress;

use function is_string;
use function str_contains;
use function strlen;

/**
 * Primitive routing, executing arbitrary code depending on $_GET parameters.
 */
class Route {
    /**
     * Name of the $_GET parameter containing the routing name.
     */
    public const ROUTE_PARAM = 'resursbank';

    /**
     * Route to get address controller.
     */
    public const ROUTE_GET_ADDRESS = 'get-address';

    /**
     * @return void
     */
    public static function exec(): void {
	    $route = (
		    isset($_GET[self::ROUTE_PARAM]) &&
		    is_string(value: $_GET[self::ROUTE_PARAM])
	    ) ? $_GET[self::ROUTE_PARAM] : '';

		try {
			switch ($route) {
				case self::ROUTE_GET_ADDRESS:
					GetAddress::exec();
					break;
				case '':
					throw new HttpException(
						message: 'Route can not be empty.',
						code: 400
					);
				default:
					throw new HttpException(
						message: "Route $route not found.",
						code: 404
					);
			}
		} catch (Exception $e) {
			self::respondWithError(exception: $e);
		}
    }

    /**
     * Resolve full URL.
     *
     * @param string $route
     * @return string
     * @throws HttpException
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
	 *
	 * @param string $body
	 * @param int $code
	 * @return void
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
	 * @param Exception $exception
	 * @return void
	 */
	public static function respondWithError(
		Exception $exception
	): void {
		$controller = new CoreController();

		self::respond(
			body: $controller->respondWithError(exception: $exception),
			code: $controller->getErrorResponseCode(exception: $exception)
		);
	}
}
