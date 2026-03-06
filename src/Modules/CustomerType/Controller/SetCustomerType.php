<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbankabpayments\Woocommerce\Modules\CustomerType\Controller;

use Resursbank\Ecom\Lib\Order\CustomerType;
use Resursbank\Ecom\Module\Customer\Repository as CustomerRepository;
use Resursbankabpayments\Woocommerce\Util\Url;
use Resursbankabpayments\Woocommerce\Util\WcSession;
use Throwable;

use function function_exists;

/**
 * AJAX controller for the Part payment widget.
 */
class SetCustomerType
{
    /**
     * Handle session storing of customer type when checkout is updated.
     */
    public static function exec(): string
    {
        $response = [
            'update' => false,
            'customerType' => null,
        ];

        try {
            $customerType = Url::getHttpGet(key: 'customerType');

            if (!function_exists(function: 'WC')) {
                return wp_json_encode(value: $response, flags: JSON_FORCE_OBJECT);
            }

            if (!$customerType) {
                return wp_json_encode(value: $response, flags: JSON_FORCE_OBJECT);
            }

            WC()->initialize_session();

            try {
                $customerTypeEnum = CustomerType::from(value: $customerType);
            } catch (Throwable) {
                // Invalid customer type value
                return wp_json_encode(value: $response, flags: JSON_FORCE_OBJECT);
            }

            if ($customerTypeEnum instanceof CustomerType) {
                // Report back if successful or not.
                $response['update'] = WcSession::set(
                    key: RESURSBANKABPAYMENTS_MODULE_PREFIX . '_' . CustomerRepository::SESSION_KEY_CUSTOMER_TYPE,
                    value: $customerTypeEnum->value
                );
                $response['customerType'] = $customerTypeEnum->value;
            }

            return wp_json_encode(value: $response, flags: JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            // Ensure we always return valid JSON even on exception
            $response['error'] = $e->getMessage();

            try {
                return wp_json_encode(value: $response, flags: JSON_FORCE_OBJECT);
            } catch (Throwable) {
                // Last resort: return minimal valid JSON
                return '{"update":false,"error":"Server error"}';
            }
        }
    }
}
