<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Ecom\Lib\Model;

use Resursbank\Ecom\Exception\Validation\EmptyValueException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Payment\ApplicationResponse;
use Resursbank\Ecom\Lib\Model\Payment\Application\CoApplicant;
use Resursbank\Ecom\Lib\Model\Payment\Customer;
use Resursbank\Ecom\Lib\Model\Payment\Metadata;
use Resursbank\Ecom\Lib\Model\Payment\Order;
use Resursbank\Ecom\Lib\Model\Payment\Order\PossibleAction as PossibleActionModel;
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
     * @param string $created
     * @param string $storeId
     * @param string $paymentMethodId
     * @param Customer $customer
     * @param Status $status
     * @param array $paymentActions
     * @param CountryCode|null $countryCode
     * @param Order|null $order
     * @param ApplicationResponse|null $application
     * @param Metadata|null $metadata
     * @param CoApplicant|null $coApplicant
     * @param TaskRedirectionUrls|null $taskRedirectionUrls
     * @param StringValidation $stringValidation
     * @throws EmptyValueException
     * @throws IllegalValueException
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     * @todo Missing unit tests ECP-254
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
        public readonly ?ApplicationResponse $application = null,
        public readonly ?Metadata $metadata = null,
        public readonly ?CoApplicant $coApplicant = null,
        public readonly ?TaskRedirectionUrls $taskRedirectionUrls = null,
        private readonly StringValidation $stringValidation = new StringValidation(),
    ) {
        $this->validateId();
        $this->validateCreated();
        $this->validateStoreId();
        $this->validatePaymentMethodId();
    }

    /**
     * NOTE: We cannot test date format because Resurs Bank will return
     * inconsistent values for the same properties (sometimes ATOM compatible,
     * sometimes containing a up to 9 digit microsecond suffix).
     *
     * @return void
     * @throws IllegalValueException
     */
    private function validateCreated(): void
    {
        $this->stringValidation->isTimestampDate(value: $this->created);
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
            /** @var PossibleActionModel $action */
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
