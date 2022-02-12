<?php

namespace ResursBank\Gateway;

use Exception;
use ResursBank\Module\Data;
use ResursBank\Service\WooCommerce;
use function count;
use function is_array;

/**
 * Class ResursCheckout RCO iframe based class, that acts like it was a real payment method.
 *
 * @package ResursBank\Gateway
 * @since 0.0.1.0
 */
class ResursCheckout
{
    /**
     * Payment method id as seen by getPaymentMethods.
     * @var string
     * @since 0.0.1.0
     */
    public $id = 'RESURS_CHECKOUT';

    /**
     * Description as seen by getPaymentMethods.
     * @var string
     * @since 0.0.1.0
     */
    public $description = 'Resurs Checkout';

    /**
     * Info links like SEKKI, etc, as seen by getPaymentMethods.
     * @var array
     * @since 0.0.1.0
     */
    public $legalInfoLinks = [];

    /**
     * Minimum payment limit as seen by getPaymentMethods.
     * @var int
     * @since 0.0.1.0
     */
    public $minLimit;

    /**
     * Maximum payment limit  as seen by getPaymentMethods.
     * @var int
     * @since 0.0.1.0
     */
    public $maxLimit = PHP_INT_MAX;

    /**
     * Payment method type as seen by getPaymentMethods, but customized.
     * @var string
     * @since 0.0.1.0
     */
    public $type = 'iframe';

    /**
     * Customer type as seen by getPaymentMethods.
     * @var string|array
     * @since 0.0.1.0
     */
    public $customerType;

    /**
     * Specific payment method type as seen by getPaymentMethods.
     * @var string
     * @since 0.0.1.0
     */
    public $specificType;

    /**
     * @return bool
     * @since 0.0.1.0
     */
    public function isLegacyIframe($iframeContainer): bool
    {
        return (
        (isset($iframeContainer, $iframeContainer->script) &&
            preg_match('/oc-shop.js/', $iframeContainer->script))
        );
    }

    /**
     * Make sure we get form field data from RCO depending on legacy state.
     *
     * @return array
     * @throws Exception
     * @since 0.0.1.0
     * @noinspection LongLine
     * @noinspection ParameterDefaultValueIsNotNullInspection
     * @noinspection PhpCSValidationInspection
     */
    public function getCustomerFieldsByApiVersion($getType = 'billingAddress'): array
    {
        $rcoCustomerData = (array)WooCommerce::getSessionValue('rco_customer_session_request');

        // Make sure that the arrays exists before validate them.
        if (WooCommerce::getSessionValue('rco_legacy')) {
            WooCommerce::setSessionValue('paymentMethod', $rcoCustomerData['paymentMethod']);
            $getType = $this->getCustomerFieldsTypeByLegacy($getType);
            $customerAddressBlock = $rcoCustomerData['customerData'][$getType] ?? [];
            $return = [
                'first_name' => !empty($customerAddressBlock['firstname']) ? $customerAddressBlock['firstname'] : '',
                'last_name' => !empty($customerAddressBlock['surname']) ? $customerAddressBlock['surname'] : '',
                'address_1' => !empty($customerAddressBlock['address']) ? $customerAddressBlock['address'] : '',
                'address_2' => !empty($customerAddressBlock['addressExtra']) ? $customerAddressBlock['addressExtra'] : '',
                'city' => !empty($customerAddressBlock['city']) ? $customerAddressBlock['city'] : '',
                'postcode' => !empty($customerAddressBlock['postal']) ? $customerAddressBlock['postal'] : '',
                'country' => !empty($customerAddressBlock['countryCode']) ? $customerAddressBlock['countryCode'] : '',
                'email' => !empty($customerAddressBlock['email']) ? $customerAddressBlock['email'] : '',
                'phone' => !empty($customerAddressBlock['telephone']) ? $customerAddressBlock['telephone'] : '',
            ];
        } else {
            $customerAddressBlock = $rcoCustomerData[$getType] ?? [];
            // This should absolutely not be empty!
            $rcoPaymentData = Data::getRequest('rco_payment') ?? [];
            WooCommerce::setSessionValue('paymentMethod', $rcoPaymentData['id']);
            WooCommerce::applyMock('customerAddressBlockException');

            if (is_array($customerAddressBlock) && count($customerAddressBlock) > 2) {
                // Observe that RCOv2 does not have any country code available, so it has to be
                // fetched elsewhere.
                $return = [
                    'first_name' => !empty($customerAddressBlock['firstName']) ? $customerAddressBlock['firstName'] : '',
                    'last_name' => !empty($customerAddressBlock['lastName']) ? $customerAddressBlock['lastName'] : '',
                    'address_1' => !empty($customerAddressBlock['addressRow1']) ? $customerAddressBlock['addressRow1'] : '',
                    'address_2' => !empty($customerAddressBlock['addressExtra']) ? $customerAddressBlock['addressExtra'] : '',
                    'city' => !empty($customerAddressBlock['city']) ? $customerAddressBlock['city'] : '',
                    'postcode' => !empty($customerAddressBlock['postalCode']) ? $customerAddressBlock['postalCode'] : '',
                    'country' => Data::getCustomerCountry(),
                    'email' => !empty($rcoCustomerData['email']) ? $rcoCustomerData['email'] : '',
                    'phone' => !empty($rcoCustomerData['phone']) ? $rcoCustomerData['phone'] : '',
                ];
            } else {
                throw new Exception(
                    __(
                        'Address block from Resurs Checkout is empty!',
                        'tornevalls-resurs-bank-payment-gateway-for-woocommerce'
                    )
                );
            }
        }
        return $return;
    }

    /**
     * @param $getType
     * @return string
     * @since 0.0.1.0
     */
    private function getCustomerFieldsTypeByLegacy($getType): string
    {
        return $getType === 'deliveryAddress' ? 'delivery' : 'address';
    }
}
