<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Gateway;

use Resursbank\Ecom\Lib\Order\PaymentMethod\Type;
use Resursbank\Woocommerce\Util\Url;
use WC_Payment_Gateway;

/**
 * This is a fake gateway, only used to show the Resurs Bank link in the
 * administration panel on the page showing all available payment gateways.
 *
 * It also enables the admin to sort our group of payment methods with other
 * payment methods. Placing for example PayPal methods above / below our
 * group of Resurs Bank methods.
 *
 * The gateway is forcibly disabled to ensure it cannot be selected by customers
 * at checkout, but will still appear in the admin list of payment methods.
 */
class ResursbankLink extends WC_Payment_Gateway
{
    public const ID = 'resursbank';

    public function __construct() {
        $this->id = self::ID;
        $this->plugin_id = 'resursbank-mapi';
        $this->title = 'Resurs Bank';
        $this->method_title = $this->title;
        $this->method_description = 'Resurs Bank Gateway';
        $this->icon = Url::getPaymentMethodIconUrl(type: Type::RESURS_INVOICE);
        $this->has_fields =  false;
        $this->enabled = 'no';
    }
}
