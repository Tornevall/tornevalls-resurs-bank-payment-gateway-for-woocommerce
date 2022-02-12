// resursbank_checkout.js --- Generic Checkout Handler.

/**
 * Transformation data container for RCO billing/delivery fields.
 * @type {{"1": {address: string, city: string, phone: string, addressExtra: string, postcode: string, last_name: string, first_name: string, email: string}, "2": {city: string, phone: null, address_1: string, address_2: string, postcode: string, last_name: string, first_name: string, email: null}}}
 * @since 0.0.1.0
 */
var rbwcCustomerTransformationContainer = {
    '1': {
        "first_name": "firstname",
        "last_name": "surname",
        "address": "address_1",
        "addressExtra": "address_2",
        "postcode": "postal",
        "city": "city",
        "phone": "telephone",
        "email": "email"
    },
    '2': {
        "first_name": "firstName",
        "last_name": "lastName",
        "address_1": "addressRow1",
        "address_2": "addressRow2",
        "postcode": "postalCode",
        "city": "city",
        "phone": null,
        "email": null
    }
};

/**
 * Boolean set depending on if RCO has a delivery address or not.
 * @type {boolean}
 * @since 0.0.1.0
 */
var rbwcHasDelivery = false;

/**
 * @since 0.0.1.0
 */
$rQuery(document).ready(function () {
    getResursGateway();
    getResursHookedBillingFields();
    getRbwcRcoMode();
});

/**
 * Make currenct checkout view ready for RCO mode (hidden billing/shipping).
 * @since 0.0.1.0
 */
function getRcoBillingSetup() {
    if (typeof trbwc_rco !== 'undefined') {
        $rQuery('.woocommerce-billing-fields').hide();
        $rQuery('.woocommerce-shipping-fields').hide();
    }
}

/**
 * Set civic number in getAddress box depending on what you wish for.
 *
 * @param o
 * @since 0.0.1.2
 */
function rbwcSetCivicNumber(o) {
    $rQuery('#resursSsnIdentification').val(o.value);
    if ($rQuery('#rbGetResursAddressButton').length > 0) {
        getResursAddress();
    }
}

/**
 * Activates RCO based functions.
 * @since 0.0.1.0
 */
function getRbwcRcoMode() {
    if (typeof trbwc_rco !== 'undefined') {
        trbwcLog('trbwc_rco is present, activating triggers for RCO comms.');
        getRcoBillingSetup();
        getRcoTriggerHook();
    }
}

/**
 * @since 0.0.1.0
 */
function getResursGateway() {
    resursGateway = {
        getLegacy: function () {
            return typeof $ResursCheckout === 'undefined';
        },
        init: function () {
            var that = this;
            $rQuery('body').on('updated_checkout', function (e, info) {
                if (typeof info.fragments !== 'undefined' && typeof info.fragments.rbwc_cart_total) {
                    resursTemporaryCartTotal = info.fragments.rbwc_cart_total;
                    resursPlaceOrderControl();
                }
            });
            $rQuery(document).ajaxStop(function () {
                that.register_payment_update();
            });
        },
        register_payment_update: function () {
            $rQuery('input[id*="payment_method_"]').each(function () {
                $rQuery('#' + this.id).on('change', function () {
                    $rQuery('body').trigger('update_checkout');
                });
            });
            $rQuery('#billing_company').on('change', function () {
                $rQuery('body').trigger('update_checkout');
            });
        },
    };
    resursGateway.init();
}

/**
 * Field key translations.
 * @type {{billing_phone: string, billing_email: string}}
 * @since 0.0.1.0
 */
var resursFieldInheritList = {
    'billing_phone': 'trbwc_phone',
    'billing_email': 'trbwc_email',
    'trbwc_mobile': 'billing_phone',
    'resursSsnIdentification': 'trbwc_government_id'
};

/**
 * Hook key pressing into regular billing address fields and inherit data to Resurs fields.
 * @since 0.0.1.0
 */
function getResursHookedBillingFields() {
    for (var inheritKey in resursFieldInheritList) {
        var inheritField = $rQuery('#' + inheritKey);
        if (inheritField.length) {
            inheritField.on('change', function () {
                if (typeof resursFieldInheritList[this.id] !== 'undefined') {
                    getResursFields('input[id^="' + resursFieldInheritList[this.id] + '"]', this.value);
                    if (resursFieldInheritList[this.id] === 'trbwc_phone') {
                        getResursFields('input[id^="trbwc_mobile"]', this.value);
                    }
                }
            });
            getResursFields('input[id^="' + resursFieldInheritList[inheritField.attr('id')] + '"]', inheritField.val());
            if (inheritKey === 'billing_phone') {
                // Use standard phone to store mobile.
                getResursFields('input[id^="trbwc_mobile"]', inheritField.val());
            }
        }
    }
}

/**
 * Forgotten customer field inheritor. Reverse variant for getResursHookedBillingFields.
 * @param o
 * @since 0.0.1.0
 */
function setBillingInherit(o) {
    var shortIdArray = o.id.split('_');
    if (shortIdArray.length === 3) {
        var inheritShort = shortIdArray[0] + '_' + shortIdArray[1];
        if (o.value !== '' && typeof resursFieldInheritList[inheritShort] !== 'undefined') {
            //$rQuery('#' + resursFieldInheritList[inheritShort]).val(o.value);
            $rQuery('input[id^=' + resursFieldInheritList[inheritShort]).val(o.value);
        }
    } else if (o.id === 'resursSsnIdentification' && o.value !== '') {
        $rQuery('input[id^=' + resursFieldInheritList[o.id]).val(o.value);
    }
}

/**
 * Automatically fetch and update address data field.
 * @param findElement
 * @param thisValue
 * @since 0.0.1.0
 */
function getResursFields(findElement, thisValue) {
    var selectElement = $rQuery(findElement);
    if (selectElement.length > 0) {
        for (var elementId = 0; elementId < selectElement.length; elementId++) {
            setResursUpdateField(selectElement[elementId], thisValue);
        }
    }
}

/**
 * The final setter.
 * @param updateElement
 * @param updateValue
 * @since 0.0.1.0
 */
function setResursUpdateField(updateElement, updateValue) {
    updateElement.value = updateValue;
}

/**
 * Handle customer type from getAddress fields.
 * @param clickedObject
 * @since 0.0.1.0
 */
function setResursGetAddressCustomerType(clickedObject) {
    resursGetAddressCustomerType = clickedObject.value;
    $rQuery('body').trigger('update_checkout');
}

/**
 * @since 0.0.1.0
 */
function getResursAddress() {
    var ssnIdentification = $rQuery('#resursSsnIdentification');
    $rQuery('#resursGetAddressSpinnify').show();
    getResursSpin('#resursGetAddressSpinnify');
    if (
        $rQuery('#resursSsnIdentification').length > 0 &&
        ssnIdentification.val() !== ''
    ) {
        getResursAjaxify(
            'post',
            'resursbank_get_address',
            {'identification': ssnIdentification.val()},
            function (response) {
                $rQuery('#resursGetAddressSpinnify').hide();
                if (response['api_error'] !== '') {
                    $rQuery('.resursGetAddressError').text(response['api_error']);
                    $rQuery('.resursGetAddressError').show();
                    $rQuery('.resursGetAddressError').delay('4000').fadeOut('medium');
                } else {
                    for (var responseKey in response) {
                        if ($rQuery('#' + responseKey).length > 0) {
                            $rQuery('#' + responseKey).val(response[responseKey]);
                        }
                    }
                    if (typeof response.billing_country !== 'undefined' && $rQuery("#billing_country").length > 0) {
                        $rQuery("#billing_country").val(response.billing_country).change();
                    }
                }
            }
        );
    }
}

/**
 * Handle rejected payments in RCO mode.
 * @param data
 * @since 0.0.1.0
 */
function setRbwcPurchaseReject(data) {
    getResursAjaxify(
        'post',
        'resursbank_purchase_reject',
        data,
        function (d) {
            setRbwcGenericError(d.message)
        }
    );
}

/**
 * Activate trigger for datasynch in RCO.
 * @since 0.0.1.0
 */
function getRcoTriggerHook() {
    $rQuery('body').on('rbwc_customer_synchronize', function (event, data) {
        setRbwcCustomerDataByVersion(data.version);
    });
    $rQuery('body').on('rbwc_purchase_reject', function (event, data) {
        setRbwcPurchaseReject(data);
    });
}

/**
 * Synchronize address fields depending on RCO version.
 * @param version
 * @since 0.0.1.0
 */
function setRbwcCustomerDataByVersion(version) {
    var useFields = rbwcCustomerTransformationContainer[version];
    rbwcHandleAddress('billing', useFields, version);
    rbwcHandleAddress('shipping', useFields, version);
}

/**
 * Toggle deliver address checkbox on and off depending on RCO.
 * @param rbwcHasDelivery
 * @since 0.0.1.0
 */
function rbwcDeliveryAddressToggle(rbwcHasDelivery) {
    var deliveryPropChecked = $rQuery('#ship-to-different-address-checkbox').prop('checked');
    if (rbwcHasDelivery && !deliveryPropChecked) {
        $rQuery('#ship-to-different-address-checkbox').click();
    }
    if (!rbwcHasDelivery && deliveryPropChecked) {
        $rQuery('#ship-to-different-address-checkbox').click();
    }
}

/**
 * Handle customer address in WooCommerce address fields depending on type.
 * @param type
 * @param fields
 * @param version
 * @since 0.0.1.0
 */
function rbwcHandleAddress(type, fields, version) {
    var billingData = {};
    var deliveryData = {};
    var phoneData = '';
    var mailData = '';
    if (version === 1) {
        billingData = resursBankRcoDataContainer.rco_customer.customerData.address;
        deliveryData = resursBankRcoDataContainer.rco_customer.customerData.delivery;
        phoneData = billingData.telephone;
        mailData = billingData.email;
        rbwcHasDelivery = getRbwcDeliveryTruth(deliveryData);
    } else if (version === 2) {
        billingData = resursBankRcoDataContainer.rco_customer.billingAddress;
        deliveryData = resursBankRcoDataContainer.rco_customer.deliveryAddress;
        phoneData = resursBankRcoDataContainer.rco_customer.phone;
        mailData = resursBankRcoDataContainer.rco_customer.email;
        rbwcHasDelivery = getRbwcDeliveryTruth(deliveryData);
    }
    rbwcDeliveryAddressToggle(rbwcHasDelivery);

    var setValue;
    for (var fieldName in fields) {
        setValue = '';
        if (version === 2 && (fieldName === 'email' || fieldName === 'phone')) {
            switch (fieldName) {
                case 'email':
                    setValue = mailData;
                    break;
                case 'phone':
                    setValue = phoneData;
                    break;
                default:
            }
        } else {
            if (type === 'billing') {
                if (typeof billingData[fields[fieldName]] !== "undefined") {
                    setValue = billingData[fields[fieldName]];
                }
            }
            if (type === 'shipping') {
                if (typeof deliveryData[fields[fieldName]] !== "undefined") {
                    setValue = deliveryData[fields[fieldName]]
                }
            }
        }
        $rQuery('#' + type + "_" + fieldName).val(setValue);
    }
}

/**
 * Find out if our delivery address container has something inside.
 * @param contentArray
 * @returns {boolean}
 * @since 0.0.1.0
 */
function getRbwcDeliveryTruth(contentArray) {
    var numKeys = 0;
    for (var contentKey in contentArray) {
        if (null !== contentArray[contentKey]) {
            numKeys++;
        }
    }
    return numKeys > 0;
}
