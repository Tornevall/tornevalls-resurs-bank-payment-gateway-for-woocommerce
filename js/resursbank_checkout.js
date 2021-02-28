/**
 * @since 0.0.1.0
 */
$rQuery(document).ready(function ($) {
    getResursGateway();
    getResursHookedBillingFields();
});

function getResursGateway() {
    resursGateway = {
        init: function () {
            var that = this;
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
        }
    };
    resursGateway.init();
}

/**
 * Fields to inherit.
 * @type {{billing_phone: string, billing_email: string}}
 * @since 0.0.1.0
 */
var inheritTo = {
    'billing_phone': 'trbwc_phone',
    'billing_email': 'trbwc_email',
};

/**
 * Hook key pressing into regular billing address fields and inherit data to Resurs fields.
 * @since 0.0.1.0
 */
function getResursHookedBillingFields() {
    for (var inheritKey in inheritTo) {
        var inheritField = $rQuery('#' + inheritKey);
        if (inheritField.length) {
            inheritField.on('change', function () {
                if (typeof inheritTo[this.id] !== 'undefined') {
                    getResursFields('input[id^="' + inheritTo[this.id] + '"]', this.value);
                    if (inheritTo[this.id] === 'trbwc_phone') {
                        getResursFields('input[id^="trbwc_mobile"]', this.value);
                    }
                }
            });
            getResursFields('input[id^="' + inheritTo[inheritField.attr('id')] + '"]', inheritField.val());
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
    var inheritTo = {
        'trbwc_phone': 'billing_phone',
        'trbwc_mobile': 'billing_phone',
        'trbwc_email': 'billing_email',
    };
    var shortIdArray = o.id.split('_');
    if (shortIdArray.length === 3) {
        var inheritShort = shortIdArray[0] + '_' + shortIdArray[1];
        if (o.value && typeof inheritTo[inheritShort] !== 'undefined') {
            $rQuery('#' + inheritTo[inheritShort]).val(o.value);
        }
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
    if (selectElement.length) {
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
    console.dir(clickedObject.value);
}