<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Api\Environment as EnvironmentEnum;
use Resursbank\Ecom\Lib\UserSettings\Field;
use Resursbank\Ecom\Module\Store\Repository as StoreRepository;
use Resursbank\Ecom\Module\UserSettings\Repository;
use Resursbank\Woocommerce\Modules\UserSettings\Reader;
use Resursbank\Woocommerce\Util\Admin;
use Resursbank\Woocommerce\Util\Log;
use Resursbank\Woocommerce\Util\ResourceType;
use Resursbank\Woocommerce\Util\Route;
use Resursbank\Woocommerce\Util\Translator;
use Resursbank\Woocommerce\Util\Url;
use Resursbank\Woocommerce\Util\WooCommerce;
use Throwable;

/**
 * API settings section.
 */
class Api
{
    public const SECTION_ID = 'api_settings';
    public const NAME_PREFIX = 'resursbank_';

    /**
     * Get translated title of tab.
     */
    public static function getTitle(): string
    {
        return Translator::translate(phraseId: 'api-settings');
    }

    /**
     * Register actions for this config section.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function init(): void
    {
        $properSection = (Admin::isSection('') || Admin::isSection(
            'api_settings'
        ));

        if (
            !Admin::isTab(tabName: RESURSBANK_MODULE_PREFIX) ||
            !$properSection
        ) {
            return;
        }

        add_action(
            'admin_enqueue_scripts',
            'Resursbank\Woocommerce\Settings\Api::initScripts'
        );
    }

    /**
     * @throws HttpException
     * @throws IllegalValueException
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function initScripts(): void
    {
        wp_register_script(
            'rb-api-admin-scripts-load',
            Url::getResourceUrl(
                module: 'Api',
                file: 'saved-updates.js'
            )
        );
        wp_enqueue_script(
            'rb-api-admin-scripts-load',
            Url::getResourceUrl(
                module: 'Api',
                file: 'saved-updates.js'
            ),
            ['jquery']
        );

        wp_enqueue_style(
            'rb-ga-css',
            Url::getResourceUrl(
                module: 'Api',
                file: 'api.css',
                type: ResourceType::CSS
            ),
            [],
            '1.0.0'
        );
    }

    /**
     * Returns settings provided by this section. These will be rendered by
     * WooCommerce to a form on the config page.
     *
     * @throws ConfigException
     */
    public static function getSettings(): array
    {
        return [
            self::SECTION_ID => [
                'enabled' => self::getEnabled(),
                'environment' => self::getEnvironment(),
                'client_id' => self::getClientId(),
                'client_secret' => self::getClientSecret(),
                'store_id' => self::getStoreIdSetting()
            ],
        ];
    }

    /**
     * Get Enabled setting array.
     */
    private static function getEnabled(): array
    {
        return [
            'id' => Reader::getOptionName(field: Field::ENABLED),
            'title' => Translator::translate(phraseId: 'enabled'),
            'type' => 'checkbox',
            'default' => Repository::getDefault(field: Field::ENABLED) ? 'yes' : 'no',
        ];
    }

    /**
     * Get Environment setting array.
     */
    private static function getEnvironment(): array
    {
        // @todo Generating the options array can be centralized to Ecom.
        return [
            'id' => Reader::getOptionName(field: Field::ENVIRONMENT),
            'title' => Translator::translate(phraseId: 'environment'),
            'type' => 'select',
            'options' => [
                EnvironmentEnum::TEST->value => Translator::translate(
                    phraseId: 'test'
                ),
                EnvironmentEnum::PROD->value => Translator::translate(
                    phraseId: 'prod'
                ),
            ],
            'custom_attributes' => ['size' => 1],
            'default' => Repository::getDefault(field: Field::ENVIRONMENT)->value,
        ];
    }

    /**
     * Get Client ID setting array.
     */
    private static function getClientId(): array
    {
        // WooCommerce has no separation for test / prod credentials right now,
        // which is why this uses the CLIENT_ID_PROD field here, prod and test
        // uses the same option name in the database so it doesn't matter which
        // we use here.
        return [
            'id' => Reader::getOptionName(field: Field::CLIENT_ID_PROD),
            'title' => Translator::translate(phraseId: 'client-id'),
            'type' => 'text',
            'default' => ''
        ];
    }

    /**
     * Get Client Secret setting array.
     */
    private static function getClientSecret(): array
    {
        return [
            'id' => Reader::getOptionName(field: Field::CLIENT_SECRET_PROD),
            'title' => Translator::translate(phraseId: 'client-secret'),
            'type' => 'password',
            'default' => '',
        ];
    }

    /**
     * Get the array for Store ID selector setting.
     */
    private static function getStoreIdSetting(): array
    {
        $result = [
            'id' => Reader::getOptionName(field: Field::STORE_ID),
            'type' => 'select',
            'title' => Translator::translate(phraseId: 'store-id'),
            'default' => '',
            'options' => [],
        ];

        try {
            // Do not fetch stores until credentials are present.
            if (Repository::hasUserCredentials()) {
                $result['options'] = StoreRepository::getStores()->getSelectList();
            }
        } catch (Throwable $error) {
            self::logAndHandleError(error: $error);
        }

        return $result;
    }

    /**
     * Log and handle visible error messages.
     */
    private static function logAndHandleError(Throwable $error): void
    {
        // Some errors cannot be rendered through the admin_notices. Avoid that action.
        Admin::getAdminErrorNote(message: $error->getMessage());
        Log::error(error: $error);
    }
}
