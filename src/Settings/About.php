<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use Resursbank\Ecom\Module\Widget\SupportInfo\Css as EcomSupportInfoCss;
use Resursbank\Ecom\Module\Widget\SupportInfo\Html as EcomSupportInfo;
use Resursbank\Woocommerce\Util\Translator;
use Resursbank\Woocommerce\Util\UserAgent;
use Throwable;

/**
 * Support info section.
 *
 * @todo CSS should be moved to controller, this file can then be deleted.
 */
class About
{
    public const SECTION_ID = 'about';

    public static ?EcomSupportInfo $widget = null;

    /**
     * Set up css for the About widget.
     */
    public static function setCss(): void
    {
        echo '<style>' . ((new EcomSupportInfoCss())->content ?? '') . "</style>\n";
    }

    /**
     * Get tab title
     */
    public static function getTitle(): string
    {
        return Translator::translate(phraseId: 'about');
    }
}
