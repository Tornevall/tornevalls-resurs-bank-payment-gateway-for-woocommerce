<?php

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Gateway;

use Resursbank\Ecom\Lib\Model\PaymentMethod;
use Resursbank\Ecom\Module\PriceSignage\Repository as GetPriceSignageRepository;
use Resursbank\Ecom\Module\PriceSignage\Widget\CostList;
use Resursbank\Ecom\Module\PriceSignage\Widget\Warning;
use Resursbank\Woocommerce\Util\Log;
use Throwable;
use WC_Cart;

/**
 * Generic class that provides both blocks and legacy with relevant methods for the gateway.
 */
class GatewayHelper
{
    /**
     * Render Cost List widget and return HTML.
     */
    public static function getCostList(PaymentMethod $method): string
    {
        try {
            $total = 0.0;

            if (WC()->cart instanceof WC_Cart) {
                $total = (float) WC()->cart->get_total();
                $totals = WC()->cart->get_totals();

                if (
                    $total === 0.0 &&
                    isset($totals['total']) &&
                    is_array($totals) &&
                    (float) $totals['total'] > 0
                ) {
                    $total = (float) $totals['total'];
                }
            }

            return '<div class="rb-ps-cl-container">' . (new CostList(
                method: $method,
                priceSignage: GetPriceSignageRepository::getPriceSignage(
                    paymentMethodId: $method->id,
                    amount: $total
                )
            ))->content . '</div>';
        } catch (Throwable $error) {
            Log::error(error: $error);
            return '';
        }
    }

    /**
     * Render Price Signage Warning widget HTML.
     */
    public static function getPriceSignageWarning(): string
    {
        try {
            return '<div class="rb-ps-warning-container">' . (new Warning())->content . '</div>';
        } catch (Throwable $error) {
            Log::error(error: $error);
            return '';
        }
    }

    /**
     * Render payment method content including Cost List and Warning widgets.
     */
    public static function renderPaymentMethodContent(PaymentMethod $method): string
    {
        return '<div class="payment-method-content">' .
            self::getCostList(method: $method) .
            self::getPriceSignageWarning() .
            '</div>';
    }
}
