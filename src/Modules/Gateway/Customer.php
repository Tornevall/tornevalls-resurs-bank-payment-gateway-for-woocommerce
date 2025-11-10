<?php

/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

declare(strict_types=1);

namespace Resursbank\Woocommerce\Modules\Gateway;

use JsonException;
use ReflectionException;
use Resursbank\Ecom\Exception\AttributeCombinationException;
use Resursbank\Ecom\Exception\ConfigException;
use Resursbank\Ecom\Exception\Validation\IllegalValueException;
use Resursbank\Ecom\Lib\Model\Address;
use Resursbank\Ecom\Lib\Model\Payment;
use Resursbank\Ecom\Lib\Model\Payment\Customer as CustomerModel;
use Resursbank\Ecom\Lib\Model\Payment\Customer\DeviceInfo;
use Resursbank\Ecom\Lib\Model\Payment\Metadata\Entry;
use Resursbank\Ecom\Lib\Order\CountryCode;
use Resursbank\Ecom\Lib\Order\CustomerType;
use Resursbank\Ecom\Module\Customer\Repository;
use Resursbank\Woocommerce\Util\WooCommerce;
use WC_Order;

/**
 * Resurs Bank payment gateway.
 */
class Customer
{
    /**
     * Retrieve Customer object for payment creation.
     *
     * @throws AttributeCombinationException
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws ConfigException
     */
    public static function getCustomer(WC_Order $order): CustomerModel
    {
        $ssnData = Repository::getSsnData();
        $address = self::getProperAddress(order: $order);
        $firstName = self::getAddressData(key: 'first_name', address: $address);
        $lastName = self::getAddressData(key: 'last_name', address: $address);

        return new CustomerModel(
            deliveryAddress: self::getDeliveryAddress(
                address: $address,
                customerType: $ssnData?->customerType,
                firstName: $firstName,
                lastName: $lastName
            ),
            customerType: $ssnData?->customerType,
            contactPerson: $ssnData?->customerType === CustomerType::LEGAL ? "$firstName $lastName" : '',
            email: $order->get_billing_email(),
            governmentId: $ssnData?->govId,
            mobilePhone: self::getCustomerPhone(order: $order),
            deviceInfo: new DeviceInfo(
                ip: DeviceInfo::getIp(),
                userAgent: DeviceInfo::getUserAgent()
            )
        );
    }

    /**
     * Return customer user id as a Resurs Bank Payment metadata entry.
     *
     * @throws IllegalValueException
     * @throws JsonException
     * @throws ReflectionException
     * @throws AttributeCombinationException
     */
    public static function getLoggedInCustomerIdMetaEntry(WC_Order $order): Payment\Metadata\Entry
    {
        if ((int)$order->get_user_id() > 0) {
            return new Entry(
                key: 'externalCustomerId',
                value: (string)$order->get_user_id()
            );
        }

        throw new IllegalValueException(
            message: 'Attempting to fetch user id on customer who is not logged in!'
        );
    }

    /**
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    public static function useAddressForBilling(WC_Order $order): bool
    {
        /*
         * If the order was created via Store API (used by the new checkout blocks),
         * the traditional 'ship_to_different_address' POST flag is not present.
         * In this case, we must infer intent by comparing billing and shipping addresses.
         */
        if ($order->is_created_via('store-api')) {
            $shippingAddress = $order->get_address('shipping');
            $billingAddress = $order->get_address();

            $compareFields = [
                'first_name',
                'last_name',
                'company',
                'address_1',
                'address_2',
                'city',
                'state',
                'postcode',
                'country',
                'phone',
                'email'
            ];

            // When use-address-for-billing is true, this is not shown in the blocks request. Neither if it is unchecked.
            // We infer billing and shipping are identical when it is checked.
            // If use-address-for-billing is false, no assumption can be made.
            // In this case, we must detect changes to see if the addresses differ.
            foreach ($compareFields as $field) {
                if (
                    isset($shippingAddress[$field], $billingAddress[$field])
                    && $shippingAddress[$field] !== $billingAddress[$field]
                ) {
                    return false;
                }
            }

            return true;
        }

        /*
         * For classic checkout (non-block), rely on the legacy POST flag.
         * If 'ship_to_different_address' is not set, assume shipping = billing.
         */
        return !isset($_POST['ship_to_different_address']);
    }

    /**
     * Get proper address based on blocks and legacy. For legacy this is very much automated by the checkbox.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    private static function getProperAddress(WC_Order $order): array
    {
        return self::useAddressForBilling(order: $order)
            ? $order->get_address()
            : $order->get_address('shipping');
    }

    /**
     * Get customer phone number.
     *
     * @noinspection PhpArgumentWithoutNamedIdentifierInspection
     */
    private static function getCustomerPhone(WC_Order $order): string
    {
        $billingPhone = $order->get_billing_phone();
        $shippingPhone = $order->get_shipping_phone();

        // Legacy only have billing phone.
        if (!WooCommerce::isUsingBlocksCheckout()) {
            return $billingPhone;
        }

        // Always use billing phone primarily when it exists. If shipping phone is empty,
        // fall back on billing.
        return !self::useAddressForBilling(order: $order) &&
        !empty($billingPhone) ? $billingPhone : ($shippingPhone ?? $billingPhone);
    }

    /**
     * @throws AttributeCombinationException
     * @throws JsonException
     * @throws ReflectionException
     */
    private static function getDeliveryAddress(
        array $address,
        ?CustomerType $customerType,
        string $firstName,
        string $lastName
    ): Address {

        $company = self::getAddressData(key: 'company', address: $address);

        $fullName = match ($customerType) {
            CustomerType::LEGAL => $company,
            default => "$firstName $lastName"
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
