<?php

/** @noinspection PhpMultipleClassDeclarationsInspection */

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Module\RcoCallback;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Config;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Module\Module as CoreModule;
use Resursbank\Ecom\Module\RcoCallback\Models\Callback;
use Resursbank\Ecom\Module\RcoCallback\Models\CallbackCollection;
use Resursbank\Ecom\Module\RcoCallback\Api\GetCallback;
use Resursbank\Ecom\Module\RcoCallback\Api\GetCallbacks;
use Resursbank\Ecom\Module\RcoCallback\Api\DeleteCallback;
use Resursbank\Ecom\Module\RcoCallback\Api\RegisterCallback;
use Resursbank\Ecom\Module\RcoCallback\Models\RegisterCallback\Request;

/**
 * Main entrypoint for interfacing with the RCO callback API programmatically
 */
class Repository extends CoreModule
{
    public const HOSTNAME_PROD = 'checkout.resurs.com';
    public const HOSTNAME_TEST = 'omnitest.resurs.com';

    /**
     * Registers a new callback
     *
     * @param string $eventName
     * @param Request $request
     * @return void
     * @throws CurlException
     * @throws EmptyValueException
     * @throws JsonException
     * @throws ValidationException
     * @throws AuthException
     * @throws IllegalTypeException
     */
    public static function registerCallback(string $eventName, Request $request): void
    {
        (new RegisterCallback())
            ->call(eventName: $eventName, request: $request);
    }

    /**
     * Gets a named callback
     *
     * @param string $eventName
     * @return Callback
     * @throws AuthException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws IllegalTypeException
     * @throws ValidationException
     */
    public static function getCallback(string $eventName): Callback
    {
        return (new GetCallback())
            ->call(eventName: $eventName);
    }

    /**
     * Gets all registered callbacks
     *
     * @return CallbackCollection
     * @throws AuthException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws IllegalTypeException
     * @throws ValidationException
     */
    public static function getCallbacks(): CallbackCollection
    {
        return (new GetCallbacks())
            ->call();
    }

    /**
     * Deletes a callback
     *
     * @param string $eventName
     * @return int
     * @throws AuthException
     * @throws CurlException
     * @throws EmptyValueException
     * @throws JsonException
     * @throws IllegalTypeException
     * @throws ValidationException
     */
    public static function deleteCallback(string $eventName): int
    {
        return (new DeleteCallback())
            ->call(eventName: $eventName);
    }

    /**
     * Gets API hostname
     *
     * @return string
     */
    public static function getApiHostname(): string
    {
        if (Config::$instance->isProduction) {
            return self::HOSTNAME_PROD;
        }

        return self::HOSTNAME_TEST;
    }
}
