<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare( strict_types=1 );

namespace Resursbank\Woocommerce\Util;

use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Lib\Http\Controller;
use Resursbank\Woocommerce\Modules\GetAddress\Controller\GetAddress;

use function str_contains;

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
            isset( $_GET[ self::ROUTE_PARAM ] ) &&
            is_string( $_GET[ self::ROUTE_PARAM ] )
        ) ? $_GET[ self::ROUTE_PARAM ] : '';

        $response = '';
        switch ( $route ) {
            case self::ROUTE_GET_ADDRESS:
                $response = GetAddress::exec();
                break;
            case '':
                break;
            default:
                $controller = new Controller();
                $response   = $controller->respondWithError(
                    exception: new HttpException( "$route is not a configured route." )
                );
        }

        self::outputHeaders( body: $response );
        echo $response;
    }

    /**
     * Outputs HTTP headers
     *
     * @param string $body
     *
     * @return void
     */
    private static function outputHeaders( string $body ): void {
        header( header: 'Content-Type: application/json' );
        header( header: 'Content-Length: ' . strlen( string: $body ) );
    }

    /**
     * Resolve full URL.
     *
     * @param string $route
     *
     * @return string
     */
    public static function getUrl(
        string $route
    ): string {
        $url = get_site_url();
        $url .= str_contains( $url, '?' ) ? '&' : '?';

        return $url . self::ROUTE_PARAM . '=' . $route;
    }
}
