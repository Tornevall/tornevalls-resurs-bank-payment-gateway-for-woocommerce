<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings\Filter;

use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Lib\Model\AnnuityFactor\AnnuityInformation;
use Resursbank\Ecom\Module\AnnuityFactor\Repository;
use Resursbank\Woocommerce\Database\Options\Advanced\StoreId;
use Resursbank\Woocommerce\Database\Options\PartPayment\PaymentMethod;
use Resursbank\Woocommerce\Database\Options\PartPayment\Period;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;

use function is_string;

/**
 * Filter to add a custom select element used for part payment period selection.
 */
class PartPaymentPeriod
{
    /**
     * Add event listener to render the custom select element.
     */
    public static function init(): void
    {
        add_action(
            'woocommerce_admin_field_rbpartpaymentperiod',
            'Resursbank\Woocommerce\Settings\Filter\PartPaymentPeriod::render'
        );
    }

    /**
     * Render the HTML element.
     *
     * @throws IllegalTypeException
     */
    public static function render(): void
    {
        $label = Translator::translate(phraseId: 'annuity-period');
        $description = Translator::translate(
            phraseId: 'part-payment-annuity-period'
        );
        $disabled = self::getPeriodDisabled() ? 'disabled' : '';
        $options = self::getPeriodOptionHtml();
        $adminUrl = get_admin_url();

        if (!is_string(value: $adminUrl)) {
            throw new IllegalTypeException(
                message: 'Fetched wp-admin URL is not a string'
            );
        }

        echo <<<EOL
<tr>
    <th scope="row" class="titledesc">
        <label for="resursbank_part_payment_period">$label</label>
    </th>
    <td class="forminp">
        <select $disabled id="resursbank_part_payment_period" name="resursbank_part_payment_period">
$options
        </select>       
        <img style="display:none;margin-top:8px;"
             id="resursbank_part_payment_period_spinner"
             src="$adminUrl/images/loading.gif"/>
        <p class="description">$description</p>
</td>
</tr>
EOL;
    }

    /**
     * Fetch annuity period options for configured payment method.
     */
    public static function getAnnuityPeriods(): array
    {
        $paymentMethodId = PaymentMethod::getData();
        $storeId = StoreId::getData();
        $annuityFactors = [];
        $return = [
            '' => Translator::translate(phraseId: 'please-select'),
        ];

        try {
            if ($paymentMethodId !== '' && $storeId !== '') {
                $annuityFactors = Repository::getAnnuityFactors(
                    paymentMethodId: $paymentMethodId
                )->getData();
            }
        } catch (Throwable) {
            MessageBag::addError(message: 'Failed to get annuity periods.');
        }

        /** @var AnnuityInformation $annuityFactor */
        foreach ($annuityFactors as $annuityFactor) {
            $return[$annuityFactor->durationMonths] = $annuityFactor->paymentPlanName;
        }

        return $return;
    }

    /**
     * Fetch the HTML block for the select options.
     */
    private static function getPeriodOptionHtml(): string
    {
        $options = '';
        $annuityPeriodId = Period::getData();

        foreach (self::getAnnuityPeriods() as $k => $v) {
            $options .= '<option value="' . esc_attr(text: $k) . '"';

            if ($annuityPeriodId === (string)$k) {
                $options .= ' selected="selected"';
            }

            $options .= '>' . esc_html(text: $v) . '</option>' . PHP_EOL;
        }

        return $options;
    }

    /**
     * Check if period input should be disabled
     */
    private static function getPeriodDisabled(): bool
    {
        $paymentMethodId = PaymentMethod::getData();
        $storeId = StoreId::getData();

        return !$paymentMethodId || !$storeId;
    }
}
