<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use Resursbank\Ecom\Lib\Model\Callback\Enum\CallbackType;
use Resursbank\Woocommerce\Database\Option;
use Resursbank\Woocommerce\Database\Options\Callback\TestReceivedAt;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\Translator;
use Resursbank\Woocommerce\Util\Url;
use Throwable;

/**
 * Callback settings section.
 */
class Callback
{
    public const SECTION_ID = 'callback';

    public const NAME_PREFIX = 'resursbank_';

    /**
     * Get translated title of tab.
     */
    public static function getTitle(): string
    {
        return Translator::translate(phraseId: 'callbacks');
    }

    /**
     * Returns settings provided by this section. These will be rendered by
     * WooCommerce to a form on the config page.
     */
    public static function getSettings(): array
    {
        return [
            self::SECTION_ID => [
                'test_button' => self::getTestButton(),
                'test_received_at' => self::getTestReceivedAt(),
                'authorization_callback_url' => self::getUrl(
                    type: CallbackType::AUTHORIZATION
                ),
                'management_callback_url' => self::getUrl(
                    type: CallbackType::MANAGEMENT
                ),
            ],
        ];
    }

    /**
     * Return URL utilised by Resurs Bank to execute callbacks.
     */
    public static function getUrl(
        CallbackType $type
    ): array {
        try {
            $typeValue = strtolower(string: $type->value);
            $title = Translator::translate(phraseId: "callback-url-$typeValue");

            return [
                'id' => self::NAME_PREFIX . $typeValue . '_callback_url',
                'type' => 'text',
                'custom_attributes' => [
                    'readonly' => 'readonly',
                ],
                'title' => $title,
                'value' => Url::getCallbackUrl(type: $type),
                'css' => 'border: none; width: 100%;',
            ];
        } catch (Throwable $error) {
            Log::error(
                error: $error,
                message: Translator::translate(
                    phraseId: 'generate-callback-template-failed'
                )
            );
        }

        return [];
    }

    /**
     * Button to execute a test callback from Resurs Bank.
     */
    private static function getTestButton(): array
    {
        return [
            'id' => Option::NAME_PREFIX . 'test_callback',
            'title' => Translator::translate(phraseId: 'test-callbacks'),
            'type' => 'rbtestcallbackbutton',
        ];
    }

    /**
     * Timestamp of last received test callback from Resurs Bank.
     */
    private static function getTestReceivedAt(): array
    {
        $time = TestReceivedAt::getData();
        $date = $time > 0 ? date(format: 'Y-m-d H:i:s', timestamp: $time) : '';

        return [
            'id' => TestReceivedAt::getName(),
            'type' => 'text',
            'custom_attributes' => [
                'readonly' => 'readonly',
            ],
            'title' => Translator::translate(
                phraseId: 'callback-test-received-at'
            ),
            'value' => $date,
            'css' => 'border: none; width: 100%;',
        ];
    }
}
