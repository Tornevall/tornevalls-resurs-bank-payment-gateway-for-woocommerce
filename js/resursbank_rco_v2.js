/**
 * Collection from RCO.
 * @type {{wooCommerce: {}, payment: {}, customer: {}}}
 * @since 0.0.1.0
 */
var resursBankRcoDataContainer = {
    rco_customer: {},
    rco_payment: {},
};

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
    if (typeof $ResursCheckout !== 'undefined') {
        var returnValue = 2;
    } else {
        returnValue = 1;
    }

    return returnValue;
}

/**
 * @since 0.0.1.0
 */
jQuery(document).ready(function ($) {
    if (typeof $ResursCheckout !== 'undefined') {
        $ResursCheckout.onSubmit(function (event) {
            // TODO: Use nonce.
            getResursAjaxify(
                'post',
                'resursbank_checkout_create_order',
                getResursWooCommerceCustomer(),
                function (response) {
                    if (response['result'] === 'success') {
                        $ResursCheckout.release();
                    }
                }
            )
        });
        $ResursCheckout.onCustomerChange(function (event) {
            resursBankRcoDataContainer.rco_customer = event
        });
        $ResursCheckout.onPaymentChange(function (event) {
            resursBankRcoDataContainer.rco_payment = event
        });
        $ResursCheckout.onPaymentFail(function (event) {
        })
        $ResursCheckout.create({
            paymentSessionId: getResursRcoContainer('paymentSessionId'),
            baseUrl: getResursRcoContainer('baseUrl'),
            hold: true,
            containerId: 'resursbank_rco_container'
        });
    }
});
