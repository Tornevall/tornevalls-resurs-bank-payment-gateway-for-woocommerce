<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use Resursbank\Ecom\Module\Widget\CallbackList\Html as CallbackListHtml;
use Resursbank\Ecom\Module\Widget\CallbackTest\Html;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\RouteVariant;
use Resursbank\Woocommerce\Util\Translator;

/**
 * Callback settings section.
 */
class Callback
{
    public const SECTION_ID = 'callback';

    /**
     * Get translated title of tab.
     */
    public static function getTitle(): string
    {
        return Translator::translate(phraseId: 'callbacks');
    }

    public static function render(): void
    {
        $GLOBALS['hide_save_button'] = '1';

        $testBtn = (new Html())->content;
        $callbackInfo = (new CallbackListHtml(
            authorizationUrl: Route::getUrl(route: RouteVariant::AuthorizationCallback)
        ))->content;

        echo $testBtn;
        echo $callbackInfo;
    }
}
