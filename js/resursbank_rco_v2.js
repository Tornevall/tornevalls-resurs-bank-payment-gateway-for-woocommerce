// Note: nonces is already active in processpayment. We don't have to use the extra layer.

/**
 * Pick up data from the primary order form and render it into a completion set for RCO.
 * @returns {{wooCommerce: {}, payment: {}, customer: {}}}
 * @since 0.0.1.0
 */
function getResursWooCommerceCustomer() {
    jQuery('[name*="checkout"] input,textarea').each(
        function (i, e) {
            if (typeof e.name !== "undefined") {
                if (e.type === "checkbox") {
                    if (e.checked === true) {
                        resursBankRcoDataContainer[e.name] = e.value;
                    } else {
                        resursBankRcoDataContainer[e.name] = 0;
                    }
                } else if (e.type === "radio") {
                    resursBankRcoDataContainer[e.name] = jQuery('[name="' + e.name + '"]:checked').val();
                } else {
                    resursBankRcoDataContainer[e.name] = e.value;
                }
            }
        }
    );
    return resursBankRcoDataContainer;
}

/**
 * Get data from RCO iframe container.
 * @param key
 * @returns {*}
 * @since 0.0.1.0
 */
function getResursRcoContainer(key) {
    let returnData;
    if (typeof trbwc_rco !== 'undefined' && typeof trbwc_rco[key] !== 'undefined') {
        returnData = trbwc_rco[key];
    }
    return returnData;
}

/**
 * Returns current Resurs Bank frontend API.
 * @returns {number}
 */
function getResursApiVersion() {
    var returnValue;
    if (typeof $ResursCheckout !== 'undefined') {
        returnValue = 2;
    } else {
        returnValue = 1;
    }

    return returnValue;
}

/**
 * @since 0.0.1.0
 */
jQuery(document).ready(function ($) {
    if (getResursLocalization('checkoutType') === 'rco' && typeof $ResursCheckout !== 'undefined') {
        $ResursCheckout.onSubmit(function (event) {
            $('body').trigger('rbwc_customer_synchronize', {
                version: 2
            });
            getResursAjaxify(
                'post',
                'resursbank_checkout_create_order',
                getResursWooCommerceCustomer(),
                function (response) {
                    if (response['result'] === 'success') {
                        $ResursCheckout.release();
                    } else {
                        if (typeof response['messages'] !== "undefined") {
                            setRbwcGenericError(response['messages'])
                        }
                    }
                },
                function (response) {
                    if (typeof response['messages'] !== "undefined") {
                        setRbwcGenericError(response['messages'])
                    }
                }
            )
        });
        $ResursCheckout.onCustomerChange(function (event) {
            resursBankRcoDataContainer.rco_customer = event
            $('body').trigger('rbwc_customer_synchronize', {
                version: 2
            });
        });
        $ResursCheckout.onPaymentChange(function (event) {
            resursBankRcoDataContainer.rco_payment = event
        });
        $ResursCheckout.onPaymentFail(function (event) {
            $('body').trigger('rbwc_purchase_reject', {type: 'fail'});
        });
        $ResursCheckout.create({
            paymentSessionId: getResursRcoContainer('paymentSessionId'),
            baseUrl: getResursRcoContainer('baseUrl'),
            hold: true,
            containerId: 'resursbank_rco_container'
        });
    }
});
