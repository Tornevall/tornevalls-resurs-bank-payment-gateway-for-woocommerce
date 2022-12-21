<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\GetAddress\Controller;

use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Lib\Utilities\Session;
use Resursbank\Ecom\Module\Customer\Http\GetAddressController;
use Resursbank\Ecom\Module\Customer\Repository;
use Resursbank\Woocommerce\Database\Options\StoreId;
use Resursbank\Woocommerce\Util\WcSession;
use Throwable;

/**
 * Controller to fetch address content.
 */
class GetAddress
{
    /**
     * @return string
     * @throws ConfigException
     * @throws HttpException
     */
    public static function exec(): string
    {
        $controller = new GetAddressController();
        $requestData = $controller->getRequestData();

        try {
            $ecomSession = new Session();
            WcSession::set(
                $ecomSession->getKey(key: Repository::SESSION_KEY_SSN_DATA),
                $requestData->govId
            );
            WcSession::set(
                $ecomSession->getKey(key: Repository::SESSION_KEY_CUSTOMER_TYPE),
                $requestData->customerType->value
            );
            $return = $controller->exec(
                storeId: StoreId::getData(),
                data: $requestData
            );
        } catch (Throwable $e) {
            // Do nothing.
            Config::getLogger()->error($e);
        }

        return $return ?? '{}';
    }
}
