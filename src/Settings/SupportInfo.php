<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use Resursbank\Ecom\Module\SupportInfo\Widget\SupportInfo as EcomSupportInfo;
use Resursbank\Woocommerce\Util\UserAgent;

class SupportInfo
{
    public const SECTION_ID = 'support_info';

    public static function getTitle(): string
    {
        return 'Support info';
    }

    public static function getWidget(): string
    {
        $GLOBALS['hide_save_button'] = '1';
        return (new EcomSupportInfo(
            pluginVersion: UserAgent::getPluginVersion()
        ))->getHtml();
    }
}
