<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Gateway;

use Resursbank\Ecom\Exception\Validation\IllegalCharsetException;
use Resursbank\Ecom\Exception\Validation\IllegalTypeException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Address;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Lib\Model\Payment\Customer as CustomerModel;
use Resursbank\Ecom\Lib\Model\Payment\Customer\DeviceInfo;
use Resursbank\Ecom\Lib\Model\Payment\Metadata\Entry;
use Resursbank\Ecom\Lib\Model\Payment\Metadata\EntryCollection;
use Resursbank\Ecom\Lib\Order\CountryCode;
use Resursbank\Ecom\Lib\Order\CustomerType;
use Resursbank\Woocommerce\Util\WcSession;
use WC_Order;

/**
 * Resurs Bank payment gateway.
 */
class Customer
{
    /**
     * Retrieve Customer object for payment creation.
     *
     * @throws IllegalValueException
     * @throws IllegalCharsetException
     */
    public static function getCustomer(WC_Order $order): CustomerModel
    {
        $address = isset($_POST['ship_to_different_address'])
            ? $order->get_address('shipping')
            : $order->get_address();
        $customerType = WcSession::getCustomerType();
        $firstName = self::getAddressData(key: 'first_name', address: $address);
        $lastName = self::getAddressData(key: 'last_name', address: $address);

        $contactPerson = match ($customerType) {
            CustomerType::LEGAL => "$firstName $lastName",
            CustomerType::NATURAL => ''
        };

        return new CustomerModel(
            deliveryAddress: self::getDeliveryAddress(
                address: $address,
                customerType: $customerType,
                firstName: $firstName,
                lastName: $lastName
            ),
            customerType: WcSession::getCustomerType(),
            contactPerson: $contactPerson,
            email: self::getAddressData(key: 'email', address: $address),
            governmentId: WcSession::getGovernmentId(),
            mobilePhone: self::getAddressData(
                key: 'mobile',
                address: $address
            ),
            deviceInfo: new DeviceInfo(
                ip: $_SERVER['REMOTE_ADDR'] ?? '',
                userAgent: $_SERVER['HTTP_USER_AGENT'] ?? ''
            )
        );
    }

    /**
     * Return customer user id as Resurs Bank Payment metadata.
     *
     * @throws IllegalTypeException
     */
    public static function getLoggedInCustomerIdMeta(WC_Order $order): ?Payment\Metadata
    {
        return (int) $order->get_user_id() > 0 ? new Payment\Metadata(
            custom: new EntryCollection(data: [
                new Entry(
                    key: 'externalCustomerId',
                    value: (string) $order->get_user_id()
                ),
            ])
        ) : null;
    }

    /**
     * @throws IllegalValueException
     * @throws IllegalCharsetException
     */
    private static function getDeliveryAddress(
        array $address,
        CustomerType $customerType,
        string $firstName,
        string $lastName
    ): Address {

        $company = self::getAddressData(key: 'company', address: $address);

        $fullName = match ($customerType) {
            CustomerType::LEGAL => $company,
            CustomerType::NATURAL => "$firstName $lastName"
        };

        return new Address(
            addressRow1: self::getAddressData(
                key: 'address_1',
                address: $address
            ),
            postalArea: self::getAddressData(
                key: 'city',
                address: $address
            ),
            postalCode: self::getAddressData(
                key: 'postcode',
                address: $address
            ),
            countryCode: CountryCode::from(
                value: self::getAddressData(
                    key: 'country',
                    address: $address
                )
            ),
            fullName: $fullName,
            firstName: $firstName,
            lastName: $lastName,
            addressRow2: self::getAddressData(
                key: 'address_2',
                address: $address
            )
        );
    }

    /**
     * Resolve customer address data from array.
     */
    private static function getAddressData(string $key, array $address): string
    {
        return $address[$key] ?? match ($key) {
            'mobile' => $address['phone'] ?? '',
            default => ''
        };
    }
}
