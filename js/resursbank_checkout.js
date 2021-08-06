/**
 * @since 0.0.1.0
 */
$rQuery(document).ready(function ($) {
    getResursGateway();
    getResursHookedBillingFields();
});

/**
 * @since 0.0.1.0
 */
function getResursGateway() {
    resursGateway = {
        getLegacy: function () {
            return typeof $ResursCheckout === 'undefined';
        },
        init: function () {
            let that = this;
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
    if (
        $rQuery('#resursSsnIdentification').length > 0 &&
        ssnIdentification.val() !== ''
    ) {
        getResursAjaxify(
            'post',
            'resursbank_get_address',
            {'identification': ssnIdentification.val()},
            function (response) {
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
                }
            }
        );
    }
}
