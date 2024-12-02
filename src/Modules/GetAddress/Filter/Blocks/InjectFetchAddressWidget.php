<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\GetAddress\Filter\Blocks;

use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Woocommerce\Modules\GetAddress\GetAddress;
use Resursbank\Woocommerce\Util\Log;
use Throwable;

/**
 * Inject fetch address widget in checkout content.
 *
 * We inject the widget this way because:
 *
 * 1. Creating a custom block for the widget isn't useful if you only intend
 * to use it in a single place, especially when the block will render static
 * content the user cannot affect through the WordPress editor.
 *
 * 2. WooCommerce Checkout page is a block of its own, which means a custom
 * WordPress block can be injected before or after it, not in the middle of it.
 * Which means that we either would need to tweak the block to make WooCommerce
 * recognize it properly, or we would need to use JavaScript to move the rendered
 * component on frontend.
 *
 * 3. By using a block we would require the integrating client to add the block
 * manually to the WooCommerce checkout page in WordPress, which may cause
 * confusion and overhead in support.
 *
 * Because of the above, it's simply easier to inject the widget directly into
 * the rendered content.
 */
class InjectFetchAddressWidget
{
    public static function exec($content): string
    {
        try {
            if (!is_string($content)) {
                throw new IllegalTypeException(
                    message: 'Content is not a string.'
                );
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
                replacement: '$1' . GetAddress::getWidget()->content,
                subject: $content
            );
        } catch (Throwable $error) {
            Log::error(error: $error);
        }

        return $content;
    }
}
