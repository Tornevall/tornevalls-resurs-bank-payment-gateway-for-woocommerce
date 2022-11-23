<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\GetAddress;

use Resursbank\Woocommerce\Modules\GetAddress\Controller\GetAddress as Controller;
use Resursbank\Woocommerce\Modules\GetAddress\Filter\Checkout as Widget;

class Module
{
	public static function setup(): void
	{
//		Controller::register();
		Widget::register();
	}
}