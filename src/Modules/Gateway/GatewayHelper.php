<?php

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Gateway;

use Resursbank\Ecom\Lib\Model\PaymentMethod;
use Resursbank\Ecom\Module\PaymentMethod\Repository as PaymentMethodRepository;
use Resursbank\Ecom\Module\PriceSignage\Repository as GetPriceSignageRepository;
use Resursbank\Ecom\Module\Widget\ConsumerCreditWarning\Html as Warning;
use Resursbank\Ecom\Module\Widget\CostList\Html as CostList;
use Resursbank\Ecom\Module\Widget\ReadMore\Html as ReadMore;
use Resursbank\Woocommerce\Util\Log;
use Throwable;

/**
 * Generic class that provides both blocks and legacy with relevant methods for the gateway.
 */
class GatewayHelper
{
    public function __construct(
        private readonly PaymentMethod $paymentMethod,
        private readonly float $amount
    ) {
    }

    /**
     * Render read more widget and return HTML.
     *
     * @return string
     */
    public function getReadMore(): string
    {
        try {
            return (new ReadMore(
                paymentMethod: $this->paymentMethod,
                amount: $this->amount
            ))->content;
        } catch (Throwable $error) {
            Log::error(error: $error);
            return '';
        }

    }
    /**
     * Render Cost List widget and return HTML.
     */
    public function getCostList(): string
    {
        try {
            if (!$this->paymentMethod->priceSignagePossible) {
                return '';
            }

            $priceSignage = GetPriceSignageRepository::getPriceSignage(
                paymentMethodId: $this->paymentMethod->id,
                amount: $this->amount
            );

            return '<div class="rb-ps-cl-container">' . (new CostList(
                priceSignage: $priceSignage,
                method: $this->paymentMethod
            ))->content . '</div>';
        } catch (Throwable $error) {
            Log::error(error: $error);
            return '';
        }
    }

    /**
     * Render Price Signage Warning widget HTML.
     */
    public function getPriceSignageWarning(): string
    {
        return '<div class="rb-ps-warning-container">' . (new Warning(
            paymentMethod: $this->paymentMethod
        ))->content . '</div>';
    }

    /**
     * Get USP widget HTML.
     */
    public function getUspWidget(): string
    {
        try {
            return '<div class="rb-usp">' .
                PaymentMethodRepository::getUniqueSellingPoint(
                    paymentMethod: $this->paymentMethod,
                    amount: $this->amount
                )->getText() .
            '</div>';
        } catch (Throwable $error) {
            Log::error(error: $error);
            return '';
        }
    }
}
