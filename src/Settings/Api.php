<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Settings;

use Exception;
use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\ApiException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\CacheException;
use Resursbank\Ecom\Exception\CollectionException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Model\Network\Auth\Jwt;
use Resursbank\Ecom\Module\Store\Models\Store;
use Resursbank\Ecom\Module\Store\Repository as StoreRepository;
use ResursBank\Service\WordPress;
use Resursbank\Woocommerce\Database\Options\ClientId;
use Resursbank\Woocommerce\Database\Options\ClientSecret;
use Resursbank\Woocommerce\Database\Options\Enabled;
use Resursbank\Woocommerce\Database\Options\Environment;
use Throwable;

/**
 * API settings section.
 *
 * @todo Translations should be moved to ECom. See WOO-802 & ECP-205.
 * @todo After refactoring, remove all error suppression (phpstan etc.).
 */
class Api
{
    public const SECTION_ID = 'api_settings';
    public const SECTION_TITLE = 'API Settings';

    /**
     * Returns settings provided by this section. These will be rendered by
     * WooCommerce to a form on the config page.
     *
     * @return array<array>
     * @todo Refactor, method is too big. WOO-896. Remove phpcs:ignore when done.
     */
    // phpcs:ignore
    public static function getSettings(): array
    {
        // Note: The yes/no values are used as booleans in woocommerce.
        return [
            self::SECTION_ID => [
                'title' => self::SECTION_TITLE,
                'enabled' => [
                    'id' => Enabled::getName(),
                    'title' => 'Gateway Enabled',
                    'type' => 'checkbox',
                    'default' => Enabled::getDefault(),
                    'desc' => 'Enabled',
                ],
                'environment' => [
                    'id' => Environment::getName(),
                    /* @phpstan-ignore-next-line */
                    'title' => __(
                        'Environment',
                        'resurs-bank-payments-for-woocommerce'
                    ),
                    'type' => 'select',
                    'options' => [
                        /* @phpstan-ignore-next-line */
                        'test' => __(
                            'Test',
                            'resurs-bank-payments-for-woocommerce'
                        ),
                        /* @phpstan-ignore-next-line */
                        'prod' => __(
                            'Production',
                            'resurs-bank-payments-for-woocommerce'
                        ),
                    ],
                    'custom_attributes' => [
                        'size' => 1,
                    ],
                    'default' => Environment::getDefault(),
                ],
                'client_id' => [
                    'id' => ClientId::getName(),
                    'title' => 'Client ID',
                    'type' => 'text',
                    'default' => ClientId::getDefault(),
                ],
                'client_secret' => [
                    'id' => ClientSecret::getName(),
                    'title' => 'Client Secret',
                    'type' => 'password',
                    'default' => ClientSecret::getDefault(),
                ]
            ],
        ];
    }

    /**
     * @throws Exception
     * @todo Use Enums and constants for scope / grant type. These should be declared in Ecom.
     * @todo Fix credentials validation. See WOO-805 & ECP-206. Refactor as well, remove phpcs:ignore after.
     */
    // phpcs:ignore
    public static function getJwt(): ?Jwt
    {
        $result = null;
        $clientId = ClientId::getData();
        $clientSecret = ClientSecret::getData();

        try {
            if ($clientId === '' || $clientSecret === '') {
                // @todo Consider throwing a more appropriate Exception.
                // @todo Since we validate credentials on several places, it should at least be centralized.
                throw new Exception(message: 'Credentials not set.');
            }

            $result = new Jwt(
                clientId: ClientId::getData(),
                clientSecret: ClientSecret::getData(),
                scope: Environment::getData() === 'test' ? 'mock-merchant-api' : 'merchant-api',
                grantType: 'client_credentials'
            );
        } catch (Throwable $e) {
            // Handle all errors the same way, regardless of the cause.
            WordPress::setGenericError(
                exception: new Exception(
                    message: $e->getMessage(),
                    previous: $e
                )
            );
        }

        return $result;
    }
}
