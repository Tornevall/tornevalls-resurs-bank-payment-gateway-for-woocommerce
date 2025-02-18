<?php

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Gateway;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\CacheException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Model\PaymentMethod;
use Resursbank\Ecom\Lib\Model\PriceSignage\PriceSignage;
use Resursbank\Ecom\Module\PriceSignage\Repository as GetPriceSignageRepository;
use Resursbank\Ecom\Module\PriceSignage\Widget\CostList;
use Resursbank\Ecom\Module\PriceSignage\Widget\Warning;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\WooCommerce;
use Throwable;
use WC_Cart;

/**
 * Generic class that provides both blocks and legacy with relevant methods for the gateway.
 */
class GatewayHelper
{
    private ?PriceSignage $priceSignage;

    public function __construct(private readonly PaymentMethod $paymentMethod)
    {
    }

    /**
     * Render Cost List widget and return HTML.
     */
    public function getCostList(): string
    {
        $return = '';

        try {
            if ($this->paymentMethod->priceSignagePossible) {
                $return = '<div class="rb-ps-cl-container">' . (new CostList(
                    priceSignage: $this->getPriceSignage()
                ))->content . '</div>';
            }
        } catch (Throwable $error) {
            Log::error(error: $error);
        }

        return $return;
    }

    /**
     * Render Price Signage Warning widget HTML.
     */
    public function getPriceSignageWarning(): string
    {
        try {
            return '<div class="rb-ps-warning-container">' . (new Warning(
                priceSignage: $this->getPriceSignage()
            ))->content . '</div>';
        } catch (Throwable $error) {
            Log::error(error: $error);
            return '';
        }
    }

    /**
     * Render payment method content including Cost List and Warning widgets.
     *
     * @throws ConfigException
     */
    public function renderPaymentMethodContent(): string
    {
        return '<div class="payment-method-content">' .
            $this->getCostList() .
            (WooCommerce::getStoreCountry() === 'SE' ?? $this->getPriceSignageWarning()) .
            '</div>';
    }

    private function getWcTotal(): float
    {
        $total = 0.0;

        if (WC()->cart instanceof WC_Cart) {
            $total = (float)WC()->cart->get_total();
            $totals = WC()->cart->get_totals();

            if (
                $total === 0.0 &&
                isset($totals['total']) &&
                is_array($totals) &&
                (float)$totals['total'] > 0
            ) {
                $total = (float)$totals['total'];
            }
        }

        return $total;
    }

    /**
     * @throws Throwable
     * @throws JsonException
     * @throws ReflectionException
     * @throws ApiException
     * @throws AuthException
     * @throws CacheException
     * @throws ConfigException
     * @throws CurlException
     * @throws ValidationException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     */
    private function getPriceSignage(): PriceSignage
    {
        return GetPriceSignageRepository::getPriceSignage(
            paymentMethodId: $this->getPaymentMethod()->id,
            amount: $this->getWcTotal()
        );
    }

    private function getPaymentMethod(): PaymentMethod
    {
        return $this->paymentMethod;
    }
}
