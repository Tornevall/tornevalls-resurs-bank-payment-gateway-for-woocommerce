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
     * Echo HTMl element displaying the URL template, utilized by Resurs Bank,
     * for order management callbacks.
     */
    public static function renderManagementUrl(): void
    {
        self::renderCallbackUrl(type: CallbackType::MANAGEMENT);
    }

    /**
     * Echo HTMl element displaying the URL template, utilized by Resurs Bank,
     * for authorization (checkout) callbacks.
     */
    public static function renderAuthorizationUrl(): void
    {
        self::renderCallbackUrl(type: CallbackType::AUTHORIZATION);
    }

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
                'authorization_callback_url' => self::getAuthorizationUrl(),
                'management_callback_url' => self::getManagementUrl(),
            ],
        ];
    }

    /**
     * Return field to display authorization callback URL template.
     */
    public static function getAuthorizationUrl(): array
    {
        $result = [];

        try {
            $result = [
                'id' => self::NAME_PREFIX . 'authorization_callback_url',
                'type' => 'div_callback_authorization',
            ];
        } catch (Throwable $e) {
            Log::error(
                error: $e,
                message: Translator::translate(
                    phraseId: 'generate-callback-template-failed'
                )
            );
        }

        return $result;
    }

    /**
     * Return field to display management callback URL template.
     */
    public static function getManagementUrl(): array
    {
        $result = [];

        try {
            $result = [
                'id' => self::NAME_PREFIX . 'management_callback_url',
                'type' => 'div_callback_management',
            ];
        } catch (Throwable $e) {
            Log::error(
                error: $e,
                message: Translator::translate(
                    phraseId: 'generate-callback-template-failed'
                )
            );
        }

        return $result;
    }

    /**
     * Render HTML element displaying URL template utilized for callbacks by
     * Resurs Bank.
     */
    private static function renderCallbackUrl(
        CallbackType $type
    ): void {
        try {
            $title = Translator::translate(
                phraseId: "callback-url-$type->value"
            );

            echo wp_kses(
                string: '<table class="form-table"><th scope="row">' . $title . '</th><td>' .
                Url::getCallbackUrl(
                    type: $type
                ) . '</td></table>',
                allowed_html: ['table' => ['class' => []], 'th' => ['scope' => []], 'td' => []]
            );
        } catch (Throwable $error) {
            Log::error(error: $error);
        }
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
        return [
            'id' => TestReceivedAt::getName(),
            'type' => 'text',
            'custom_attributes' => [
                'readonly' => 'readonly',
            ],
            'title' => Translator::translate(
                phraseId: 'callback-test-received-at'
            ),
        ];
    }
}
