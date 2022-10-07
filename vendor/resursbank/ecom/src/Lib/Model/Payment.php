<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model;

use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalCharsetException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Payment\Application;
use Resursbank\Ecom\Lib\Model\Payment\CoApplicant;
use Resursbank\Ecom\Lib\Model\Payment\Customer;
use Resursbank\Ecom\Lib\Model\Payment\Information;
use Resursbank\Ecom\Lib\Model\Payment\Metadata;
use Resursbank\Ecom\Lib\Model\Payment\Order;
use Resursbank\Ecom\Lib\Model\Payment\TaskRedirectionUrls;
use Resursbank\Ecom\Lib\Order\CountryCode;
use Resursbank\Ecom\Lib\Validation\StringValidation;
use Resursbank\Ecom\Module\Payment\Enum\PossibleAction;
use Resursbank\Ecom\Module\Payment\Enum\Status;

/**
 * Payment model used in the GET /payment call.
 */
class Payment extends Model
{
    /**
     * Payment data container that is also used by Search. When Search is active, some
     * returned fields are not guaranteed to be present; those fields are also nullable.
     * Application and countryCode is currently not showing in Search, so to make
     * Search compatible with the Payment model, we are temporary setting the missing fields
     * with empty defaults.
     *
     * @param string $id
     * @param string $created Stringed timestamp.
     * @param string $storeId
     * @param string $paymentMethodId
     * @param Customer $customer
     * @param Status $status
     * @param array $paymentActions
     * @param CountryCode|null $countryCode
     * @param Order|null $order
     * @param Application|null $application
     * @param Information|null $information
     * @param Metadata|null $metadata
     * @param CoApplicant|null $coApplicant
     * @param TaskRedirectionUrls|null $taskRedirectionUrls
     * @param StringValidation $stringValidation
     * @throws EmptyValueException
     * @throws IllegalValueException
     * @todo Solve problems with empty country code when using Search.
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $created,
        public readonly string $storeId,
        public readonly string $paymentMethodId,
        public readonly Customer $customer,
        public readonly Status $status,
        public readonly array $paymentActions = [],
        public readonly ?CountryCode $countryCode = null,
        public readonly ?Order $order = null,
        public readonly ?Application $application = null,
        public readonly ?Information $information = null,
        public readonly ?Metadata $metadata = null,
        public readonly ?CoApplicant $coApplicant = null,
        public readonly ?TaskRedirectionUrls $taskRedirectionUrls = null,
        private readonly StringValidation $stringValidation = new StringValidation(),
    ) {
        $this->validateId();
        $this->validateCreated();
        $this->validateStoreId();
        $this->validatePaymentMethodId();
        // Validation on country code will fail when request is running through the Search call.
    }

    /**
     * Validate country.
     *
     * @todo Solve problems with empty country code when using Search.
     * @throws EmptyValueException|IllegalCharsetException
     * @noinspection PhpUnusedPrivateMethodInspection
     * @SuppressWarnings(PHPMD.UnusedPrivateMethod)
     * @phpstan-ignore-next-line
     */
    private function validateCountryCode(): void
    {
        if ($this->countryCode !== null) {
            $this->stringValidation->notEmpty(value: $this->countryCode);
            $this->stringValidation->matchRegex(
                value: $this->countryCode,
                pattern: '/^[A-Z]{2}$/'
            );
        }
    }

    /**
     * @throws IllegalValueException
     */
    private function validateCreated(): void
    {
        $this->stringValidation->isIso8601DateTime(value: $this->created);
    }

    /**
     * @return void
     * @throws EmptyValueException
     * @throws IllegalValueException
     */
    private function validatePaymentMethodId(): void
    {
        $this->validateUuid(uuid: $this->paymentMethodId);
    }

    /**
     * Validate that an (uu)id exists on the payment.
     *
     * @return void
     * @throws EmptyValueException
     * @throws IllegalValueException
     */
    private function validateId(): void
    {
        $this->validateUuid(uuid: $this->id);
    }

    /**
     * Validate existing store (uu)id.
     *
     * @return void
     * @throws EmptyValueException
     * @throws IllegalValueException
     */
    private function validateStoreId(): void
    {
        $this->validateUuid(uuid: $this->storeId);
    }

    /**
     * Validate that a string is an uuid and not empty.
     *
     * @param string $uuid
     * @return void
     * @throws EmptyValueException
     * @throws IllegalValueException
     */
    private function validateUuid(string $uuid): void
    {
        $this->stringValidation->notEmpty(value: $uuid);
        $this->stringValidation->isUuid(value: $uuid);
    }

    /**
     * Check if specified PossibleAction can be performed on this Payment
     * @param PossibleAction $actionType
     * @return bool
     */
    private function canPerformAction(PossibleAction $actionType): bool
    {
        if ($this->order) {
            foreach ($this->order->possibleActions as $action) {
                if ($action->action === $actionType) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Checks if payment can be cancelled
     *
     * @return bool
     */
    public function canCancel(): bool
    {
        return $this->canPerformAction(actionType: PossibleAction::CANCEL);
    }

    /**
     * Checks if payment can be captured
     *
     * @return bool
     */
    public function canCapture(): bool
    {
        return $this->canPerformAction(actionType: PossibleAction::CAPTURE);
    }

    /**
     * Checks if payment can be refunded
     *
     * @return bool
     */
    public function canRefund(): bool
    {
        return $this->canPerformAction(actionType: PossibleAction::REFUND);
    }

    /**
     * Alias for canRefund
     *
     * @return bool
     */
    public function canCredit(): bool
    {
        return $this->canRefund();
    }

    /**
     * Returns true if payment is frozen
     *
     * @return bool
     */
    public function isFrozen(): bool
    {
        return $this->status === Status::FROZEN;
    }
}
