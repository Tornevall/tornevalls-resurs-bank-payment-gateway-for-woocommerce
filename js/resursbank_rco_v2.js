/**
 * Collection from RCO.
 * @type {{wooCommerce: {}, payment: {}, customer: {}}}
 * @since 0.0.1.0
 */
var resursBankRcoDataContainer = {
    customer: {},
    payment: {},
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
 * @since 0.0.1.0
 */
jQuery(document).ready(function ($) {
    if (typeof $ResursCheckout !== 'undefined') {
        $ResursCheckout.onSubmit(function (event) {
            // TODO: Use nonce.
            getResursAjaxify('post', 'resursbank_checkout_create_order', getResursWooCommerceCustomer(),
                function (response) {
                    console.dir(response)
                }
            )
        })
        $ResursCheckout.onCustomerChange(function (event) {
            resursBankRcoDataContainer.customer = event
        });
        $ResursCheckout.onPaymentChange(function (event) {
            resursBankRcoDataContainer.payment = event
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
