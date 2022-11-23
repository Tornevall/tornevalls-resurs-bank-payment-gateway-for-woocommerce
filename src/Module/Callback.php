<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace ResursBank\Module;

use Resursbank\Ecom\Exception\HttpException;
use Resursbank\Ecom\Lib\Model\Callback\Enum\CallbackType;
use Resursbank\Ecom\Module\Callback\Http\AuthorizationController;
use Resursbank\Ecom\Module\Callback\Http\ManagementController;

/**
 * Callback handling by automation.
 */
class Callback
{
    /**
     * Callback automation goes here.
     * @throws HttpException
     */
    public static function processCallback(CallbackType $callbackType): void
    {
        if ($callbackType === CallbackType::AUTHORIZATION) {
            self::processAuthorization();
        } elseif ($callbackType === CallbackType::MANAGEMENT) {
            self::processManagement();
        }
    }

    /**
     * @throws HttpException
     */
    public static function processAuthorization()
    {
        $callbackModel = (new AuthorizationController())->getRequestModel();

        return [
            'svar_till_woocommerce' => 'blah'
        ];
    }

    /**
     * @throws HttpException
     */
    public static function processManagement()
    {
        $callbackModel = (new AuthorizationController())->getRequestModel();
    }
}