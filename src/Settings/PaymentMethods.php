<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use Resursbank\Ecom\Module\PaymentMethod\Repository;
use Resursbank\Ecom\Module\PaymentMethod\Widget\PaymentMethods as PaymentMethodsWidget;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Translator;
use Throwable;

/**
 * Payment methods section.
 *
 * @todo Translations should be moved to ECom. See WOO-802 & ECP-205.
 */
class PaymentMethods
{
    public const SECTION_ID = 'payment_methods';

    /**
     * Get translated title of API Settings tab on config page.
     */
    public static function getTitle(): string
    {
        return Translator::translate(phraseId: 'payment-methods');
    }

    /**
     * Outputs a template string of a table with listed payment methods.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public static function getOutput(string $storeId): string
    {
        try {
            // Hide the "Save changes" button since there are no fields here.
            $GLOBALS['hide_save_button'] = '1';

            return (new PaymentMethodsWidget(
                paymentMethods: Repository::getPaymentMethods(storeId: $storeId)
            ))->content;
        } catch (Throwable $e) {
            Log::error(
                error: $e,
                msg: Translator::translate(
                    phraseId: 'payment-methods-widget-render-failed'
                )
            );
        }

        return '';
    }
}
