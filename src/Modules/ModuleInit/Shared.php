<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\ModuleInit;

use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Lib\Model\PaymentMethod;
use Resursbank\Ecom\Lib\UserSettings\Field;
use Resursbank\Ecom\Module\PaymentMethod\Repository as PaymentMethodRepository;
use Resursbank\Ecom\Module\UserSettings\Repository;
use Resursbank\Woocommerce\Modules\Gateway\Gateway;
use Resursbank\Woocommerce\Modules\Gateway\ResursbankLink;
use Resursbank\Woocommerce\Modules\GetAddress\GetAddress;
use Resursbank\Woocommerce\Modules\MessageBag\MessageBag;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Route;
use Throwable;
use WC_Order;

/**
 * Module initialization class for functionality shared between both the frontend and wp-admin.
 */
class Shared
{
    /**
     * Init various modules.
     *
     * @throws ConfigException
     * @todo The enable check can be moved to the init.php file instead, so we do not need it in the Frontend init, the Admin init and the Shared init.
     */
    public static function init(): void
    {
        // Things that should be available even without the plugin API being enabled.
        Route::exec();
        MessageBag::init();

        self::setupPaymentMethodSortOrderHook();

        if (!Repository::isEnabled(field: Field::ENABLED)) {
            return;
        }

        // Assets must be enqueued, not called directly.
        add_action(
            'wp_enqueue_scripts',
            'Resursbank\Woocommerce\Modules\GetAddress\Filter\AssetLoader::init'
        );

        Gateway::init();
        GetAddress::init();
    }

    /**
     * In WooCommerce, when payment methods are sorted in the administration
     * panel (from the Payments tab in WooCommerce settings), the order applied
     * for the gateways listed there is stored in the wp_options table under
     * the 'woocommerce_gateway_order' option. However, since Resurs Bank
     * payment methods are added dynamically, they do not appear in
     * that list, and thus cannot be sorted by the administrator. To mitigate
     * this, we hook into the update of that option, and when our gateway
     * link is found in the list, we append our payment methods directly
     * after it, adjusting the sort order of any subsequent payment methods
     * accordingly.
     *
     * This ensures that Resurs Bank payment methods always appear directly
     * after the Resurs Bank gateway link in the payment methods list. The link
     * gateway is a faked gateway that does not appear to customers, but
     * serves as a placeholder in the payment methods list for where Resurs
     * Bank payment methods should be positioned.
     *
     * This is to allow administrators to control the sort order of Resurs Bank
     * payment methods relative to other payment methods in WooCommerce, while
     * ensuring that all methods supplied by Resurs Bank remain grouped together
     * and in the same order as specified in Merchant Portal.
     *
     * @return void
     */
    public static function setupPaymentMethodSortOrderHook(): void
    {
        add_action('pre_update_option_woocommerce_gateway_order', function($new_value, $old_value, $option) {
            // Resolve list of payment methods from Resurs Bank, in case this
            // fails just return the currently configured sorting value as is.
            try {
                $methods = PaymentMethodRepository::getPaymentMethods();
            } catch (Throwable $error) {
                Log::error(error: $error);
                return $new_value;
            }

            // First, cleanup $new_value, removing our payment methods to ensure
            // we do not get conflicting indexes when we add them back below.
            //
            // This is necessary because, when added to this list, a value is
            // never removed by WooCommerce. So, if our payment methods exist
            // in some customer database right now with inaccurate sort order
            // values, they will remain there forever, causing duplicates when
            // we inject ours now. This may also cause sorting to be incorrect.
            //
            // This could potentially be solved by a script that runs once to
            // clean up existing databases, however, this approach is better
            // since it mitigates scenarios like this:
            //
            // 1. Admin installs plugin, payment methods are added with
            //    default sort order.
            // 2. Admin adjusts sort order in WooCommerce settings.
            // 3. Admin inactivates some method in Merchant Portal.
            // 4. Admin returns to WooCommerce settings and changes sort order
            //    again of the methods.
            // 5. Merchant re-activates the previously inactivated method in
            //    Merchant Portal.
            // 6. Unless we clean the array like below, the re-activated method
            //    would now appear twice in the sort order list, and its sorting
            //    on frontend would likely be incorrect.
            /** @var PaymentMethod $method */
            foreach ($methods as $method) {
                if (isset($new_value[$method->id])) {
                    unset($new_value[$method->id]);
                }
            }

            if ($option === 'woocommerce_gateway_order') {
                // False until we find our gateway link. When this becomes true,
                // all subsequent payment methods need to have their order
                // index adjusted to ensure they appear after our methods.
                $resursbankDiscovered = false;
                $num_methods = count(value: $methods);

                // Loop through new value, and look for our gateway link.
                foreach ($new_value as $gateway => $order) {
                    if ($gateway === ResursbankLink::ID) {
                        // Add back our payment methods, ensuring they get added
                        // after our gateway link.
                        /** @var PaymentMethod $method */
                        for ($i = 0; $i < count(value: $methods); $i++) {
                            $new_value[$methods[$i]->id] = $order + 1 + $i;
                        }

                        // Flag that we found our gateway, so all subsequent
                        // payment methods can have their order adjusted.
                        $resursbankDiscovered = true;

                        // Do not modify the index of the gateway link itself.
                        continue;
                    }

                    // Update the order index of all subsequent payment methods
                    // to ensure they appear after our methods.
                    if ($resursbankDiscovered) {
                        $new_value[$gateway] += $num_methods;
                    }
                }
            }

            return $new_value;
        }, 10, 3);
    }

    /**
     * Registers filters related to payment status handling.
     * Not in use - for the moment.
     *
     * @noinspection PhpUnusedPrivateMethodInspection
     * @todo Currently not in use. Should be used to handle payment status changes on future decision.
     */
    private static function registerStatusFilters(): void
    {
        // This filter handles a maximum of 4 arguments, but since we only use this internally, we onlu use two of
        // them. Creating own filters, however, may require all 4 arguments to make sure payment and order data is
        // correct, before triggering further actions. This filter should be used with caution.
        add_filter(
            'resurs_payment_task_status',
            static fn (string $status, $taskStatusDetails, Payment $payment, WC_Order $order): string => 'cancelled',
            10,
            4
        );
    }
}
