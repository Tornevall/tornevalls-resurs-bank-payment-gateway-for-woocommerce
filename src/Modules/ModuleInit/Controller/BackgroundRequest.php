<?php

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\ModuleInit\Controller;

use Resursbank\Woocommerce\Modules\Api\Connection;
use Resursbank\Woocommerce\Modules\Gateway\Gateway;
use Resursbank\Woocommerce\Modules\Gateway\GatewayHelper;
use Resursbank\Woocommerce\Util\WooCommerce;
use Throwable;

/**
 * Background Requests.
 */
class BackgroundRequest
{
    /**
     * Run and execute data for background caching.
     *
     * @return string
     */
    public function exec(): string
    {
        $isWcPresent = WooCommerce::isWcPresent();

        try {
            if ($isWcPresent && Connection::hasCredentials()) {
                WooCommerce::endConnection(message: json_encode(value: [
                    'status' => 'exec-nodelay'
                ], flags: JSON_FORCE_OBJECT | JSON_THROW_ON_ERROR));
                WC()->initialize_session();
                $wcTotal = WooCommerce::getCartTotals();

                if (
                    !$wcTotal &&
                    (
                        isset($_REQUEST['c']) &&
                        (float)$_REQUEST['c']
                    )
                ) {
                    $wcTotal = (float)$_REQUEST['c'];
                }

                if ($wcTotal > 0) {
                    $paymentMethods = Gateway::getPaymentMethodList();

                    foreach ($paymentMethods as $paymentMethod) {
                        if (!$paymentMethod->priceSignagePossible) {
                            continue;
                        }

                        $gatewayHelper = new GatewayHelper(
                            paymentMethod: $paymentMethod,
                            amount: $wcTotal
                        );
                        $gatewayHelper->renderPaymentMethodContent(
                            paymentMethod: $paymentMethod
                        );
                    }
                }
            }
        } catch (Throwable $e) {
            Connection::getWcLoggerCritical(
                message: $e->getMessage(),
                loggerType: 'warning'
            );
        }

        echo json_encode(value: [
            'status' => 'unavailable'
        ]);
        die;
    }
}
