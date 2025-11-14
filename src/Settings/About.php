<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Module\Widget\SupportInfo\Css as EcomSupportInfoCss;
use Resursbank\Ecom\Module\Widget\SupportInfo\Html as EcomSupportInfo;
use Resursbank\Woocommerce\Util\Translator;
use Resursbank\Woocommerce\Util\UserAgent;

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

    /**
     * @throws ConfigException
     * @throws FilesystemException
     * @throws IllegalValueException
     */
    public static function render(): void
    {
        $GLOBALS['hide_save_button'] = '1';

        echo (new EcomSupportInfo(
            minimumPhpVersion: '8.1',
            maximumPhpVersion: '8.4',
            pluginVersion: UserAgent::getPluginVersion()
        ))->content;
    }
}
