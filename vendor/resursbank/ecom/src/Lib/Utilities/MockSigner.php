<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

/** @noinspection PhpMultipleClassDeclarationsInspection */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Utilities;

use JsonException;
use Resursbank\Ecom\Exception\AuthException;
use Resursbank\Ecom\Exception\CurlException;
use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\ValidationException;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Lib\Network\AuthType;
use Resursbank\Ecom\Lib\Network\ContentType;
use Resursbank\Ecom\Lib\Network\Curl;
use Resursbank\Ecom\Lib\Network\RequestMethod;

/**
 * Handles mock signing in dev
 */
class MockSigner
{
    /**
     * @param Payment $payment
     * @return void
     * @throws EmptyValueException
     * @throws JsonException
     * @throws AuthException
     * @throws CurlException
     * @throws ValidationException
     * @throws IllegalTypeException
     */
    public static function approve(Payment $payment): void
    {
        if (!$payment->taskRedirectionUrls) {
            throw new EmptyValueException(message: 'No redirection URL object found');
        }
        $curl = new Curl(
            url: $payment->taskRedirectionUrls->customerUrl,
            requestMethod: RequestMethod::GET,
            contentType: ContentType::URL,
            authType: AuthType::NONE,
            responseContentType: ContentType::RAW
        );
        $curl->exec();
        $redirectUrl = $curl->getEffectiveUrl();
        $realAuthUrl = str_replace(
            search: 'authenticate',
            replace: 'doAuth',
            subject: $redirectUrl
        ) . '&govId=' . $payment->customer->governmentId;

        $curl = new Curl(
            url: $realAuthUrl,
            requestMethod: RequestMethod::GET,
            contentType: ContentType::EMPTY,
            authType: AuthType::NONE,
            responseContentType: ContentType::RAW
        );
        $curl->exec();
    }
}
