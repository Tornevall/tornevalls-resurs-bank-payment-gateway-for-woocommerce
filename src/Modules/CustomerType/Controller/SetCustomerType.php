<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\CustomerType\Controller;

use Resursbank\Ecom\Lib\Order\CustomerType;
use Resursbank\Ecom\Lib\Utilities\Session;
use Resursbank\Ecom\Module\Customer\Repository as CustomerRepository;
use Resursbank\Woocommerce\Util\Url;
use Resursbank\Woocommerce\Util\WcSession;
use Throwable;
use function function_exists;

/**
 * AJAX controller for the Part payment widget
 */
class SetCustomerType
{
    /**
     * Handle session storing of customer type when checkout is updated.
     * @return string
     */
    public static function exec(): string
    {
        $response = [
            'update' => false,
        ];
        $customerType = Url::getHttpGet(key: 'customerType');

        if (function_exists(function: 'WC') && $customerType) {
            $ecomSession = new Session();
            WC()->initialize_session();
            $customerType = CustomerType::from(value: $customerType);

            if ($customerType instanceof CustomerType) {
                // Report back if successful or not.
                $response['update'] = WcSession::set(
                    key: $ecomSession->getKey(
                        key: CustomerRepository::SESSION_KEY_CUSTOMER_TYPE
                    ),
                    value: $customerType->value
                );
            }
        }

        try {
            return json_encode(
                value: $response,
                flags: JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR
            );
        } catch (Throwable) {
            return '';
        }
    }
}
