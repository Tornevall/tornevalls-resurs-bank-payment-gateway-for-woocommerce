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

    /**
     * @SuppressWarnings(PHPMD.EmptyCatchBlock)
     */
    public static function getWidget(): ?EcomSupportInfo
    {
        try {
            if (self::$widget === null) {
                self::$widget = new EcomSupportInfo(
                    minimumPhpVersion: '8.1',
                    maximumPhpVersion: '8.4',
                    pluginVersion: UserAgent::getPluginVersion()
                );
            }
        } catch (Throwable) {
        }

        return self::$widget;
    }

    /**
     * Create and return widget HTML.
     *
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public static function getWidgetHtml(): string
    {
        $GLOBALS['hide_save_button'] = '1';
        return self::getWidget()->content;
    }
}
