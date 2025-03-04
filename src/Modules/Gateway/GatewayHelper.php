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
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\TranslationException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Model\PaymentMethod;
use Resursbank\Ecom\Lib\Model\PriceSignage\PriceSignage;
use Resursbank\Ecom\Module\PaymentMethod\Widget\ReadMore;
use Resursbank\Ecom\Module\PriceSignage\Repository as GetPriceSignageRepository;
use Resursbank\Ecom\Module\PriceSignage\Widget\CostList;
use Resursbank\Ecom\Module\PriceSignage\Widget\Warning;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\WooCommerce;
use Throwable;

/**
 * Generic class that provides both blocks and legacy with relevant methods for the gateway.
 */
class GatewayHelper
{
    /**
     * WooCommerce amount to work with.
     */
    private float $amount = 0.0;

    /**
     * How long transients should remain before expire.
     */
    private int $transientExpireTime = 600;

    public function __construct(public readonly PaymentMethod $paymentMethod, float $amount = 0.0)
    {
        $this->amount = $amount > 0.0 ? $amount : WooCommerce::getCartTotals();
    }

    /**
     * Render payment method content including Cost List and Warning widgets.
     *
     * @throws ConfigException
     * @throws FilesystemException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws TranslationException
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public function renderPaymentMethodContent(
        PaymentMethod $paymentMethod
    ): string {

        // Fixing performance issues on reloads. Loading content this way significantly improves efficiency.
        // Payment method content needs a separate transient storage for the pre-caching system.
        $transientName = sprintf(
            '%s_method_content_%s_%s',
            RESURSBANK_MODULE_PREFIX,
            $this->getPaymentMethod()->id,
            $this->amount
        );
        $transientContent = get_transient($transientName);

        if ($transientContent) {
            return $transientContent;
        }

        $return = '<div class="payment-method-content">' .
            $this->getCostList() .
            (new ReadMore(
                paymentMethod: $paymentMethod,
                amount: $this->amount
            ))->content .
            $this->getPriceSignageWarning() .
            '</div>';

        set_transient($transientName, $return, $this->getTransientExpiration());

        return $return;
    }

    /**
     * Expire time setup for transients related to cost-list and priceSignage.
     */
    public function getTransientExpiration(): int
    {
        $expiration = $this->transientExpireTime ?? 600;
        return (int)apply_filters(
            'rb_cost_transient_expire',
            is_numeric(value: $expiration) ? $expiration : 600
        );
    }

    /**
     * Render Cost List widget and return HTML.
     */
    public function getCostList(): string
    {
        $return = '';

        try {
            if ($this->paymentMethod->priceSignagePossible) {
                $return = $this->getCostListHtml();
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
            return $this->paymentMethod->priceSignagePossible && WooCommerce::getStoreCountry() === 'SE' ?
                '<div class="rb-ps-warning-container">' . (new Warning(
                    priceSignage: $this->getPriceSignage(),
                    paymentMethod: $this->paymentMethod
                ))->content . '</div>' : '';
        } catch (Throwable $error) {
            Log::error(error: $error);
            return '';
        }
    }

    /**
     * @throws ApiException
     * @throws AuthException
     * @throws CacheException
     * @throws ConfigException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws IllegalTypeException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws Throwable
     * @throws ValidationException
     * @throws FilesystemException
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    private function getCostListHtml(): string
    {
        // Fixing performance issues on reloads. Loading content this way significantly improves efficiency.
        $transientName = sprintf(
            '%s_cost_list_%s_%s',
            RESURSBANK_MODULE_PREFIX,
            $this->getPaymentMethod()->id,
            $this->amount
        );
        $transientContent = get_transient($transientName);

        if ($transientContent) {
            return $transientContent;
        }

        if (!$this->paymentMethod->priceSignagePossible) {
            return '';
        }

        $return = '<div class="rb-ps-cl-container">' . (new CostList(
            priceSignage: $this->getPriceSignage(),
            method: $this->paymentMethod
        ))->content . '</div>';

        set_transient($transientName, $return, $this->getTransientExpiration());

        return $return;
    }

    /**
     * Get current payment method.
     */
    private function getPaymentMethod(): PaymentMethod
    {
        return $this->paymentMethod;
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
            amount: $this->amount
        );
    }
}
