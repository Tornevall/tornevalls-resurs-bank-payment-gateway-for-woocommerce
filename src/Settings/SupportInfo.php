<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use Resursbank\Ecom\Exception\FilesystemException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Module\SupportInfo\Widget\SupportInfo as EcomSupportInfo;
use Resursbank\Woocommerce\Util\Translator;
use Resursbank\Woocommerce\Util\UserAgent;

/**
 * Support info section.
 */
class SupportInfo
{
    public const SECTION_ID = 'support_info';

    /**
     * Get tab title
     */
    public static function getTitle(): string
    {
        return Translator::translate(phraseId: 'support-info');
    }

    /**
     * Create and return widget HTML.
     *
     * @throws FilesystemException
     * @throws IllegalValueException
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public static function getWidget(): string
    {
        $GLOBALS['hide_save_button'] = '1';
        return (new EcomSupportInfo(
            pluginVersion: UserAgent::getPluginVersion()
        ))->getHtml();
    }
}
