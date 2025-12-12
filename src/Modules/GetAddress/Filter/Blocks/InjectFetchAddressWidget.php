<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\GetAddress\Filter\Blocks;

use Resursbank\Ecom\Lib\Log\Logger;
use Resursbank\Ecom\Module\Widget\GetAddress\Html as Widget;
use Throwable;

/**
 * Inject the fetch address widget into the checkout content.
 *
 * We inject the widget this way because:
 *
 * 1. Creating a custom block for the widget isn't useful if you only intend
 *    to use it in a single place, especially when the block will render static
 *    content the user cannot modify through the WordPress editor.
 *
 * 2. The WooCommerce Checkout page is a block of its own, which means a custom
 *    WordPress block can be injected before or after it, but not in the middle
 *    of it. This means that we would either need to tweak the block to make
 *    WooCommerce recognize it properly, or we would need to use JavaScript to
 *    move the rendered component on the frontend.
 *
 * 3. By using a block, we would require the integrating client to add the block
 *    manually to the WooCommerce checkout page in WordPress, which may cause
 *    confusion and increase support overhead.
 *
 * Because of the above, it's simply easier to inject the widget directly into
 * the rendered content.
 */
class InjectFetchAddressWidget
{
    /**
     * Initialize the fetchAddress widget.
     */
    public static function exec(mixed $content): string
    {
        try {
            // Not clear if we really need to protect this section.
            //if (!function_exists(function: 'is_checkout') || !is_checkout()) {
            //    return $content;
            //}

            // Since this is a filter hook executed by the_content, we cannot be sure that the
            // content really is string and throw errors back to the frontend from here.
            // If we get anything but a string, we will therefore only silently return it.
            // We don't know what's installed here, beyond WooCommerce and the rest of the platform.
            if (!is_string(value: $content)) {
                return $content;
            }

            if (
                !preg_match(
                    pattern: '/<div[^>]*data-block-name="woocommerce\/checkout"[^>]*>/',
                    subject: $content
                )
            ) {
                return $content;
            }

            $content = preg_replace(
                pattern: '/(<div[^>]*data-block-name="woocommerce\/checkout-contact-information-block"[^>]*><\/div>)/',
                replacement: '$1' . (new Widget())->content,
                subject: $content
            );
        } catch (Throwable $error) {
            Logger::error(message: $error);
        }

        return $content;
    }
}
