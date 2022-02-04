// Note: nonces is already active in processpayment. We don't have to use the extra layer.

jQuery(document).ready(function ($) {
    RESURSCHECKOUT_IFRAME_URL = getResursRcoContainer('originHostName');
    if (getResursLocalization('checkoutType') === 'rco') {
        trbwcLog('Resurs Checkout JSAPI Version Detected: ' + getResursApiVersion());
        if (getResursApiVersion() === 1 && typeof ResursCheckout === 'function') {
            getRbwcLegacyInit('#resursbank_rco_container');
        }
    }
});

/**
 *
 * @param rcoLegacyElement
 * @since 0.0.1.0
 */
function getRbwcLegacyInit(rcoLegacyElement) {
    trbwcLog('Initializing rcoLegacyElement by ' + rcoLegacyElement + ' (Using ' + RESURSCHECKOUT_IFRAME_URL + ').');
    var rcoLegacy = ResursCheckout(rcoLegacyElement);
    rcoLegacy.init();

    // Iframe and script element won't load before anything else on the site has been loaded.
    $rQuery(rcoLegacyElement).html(getResursRcoContainer('html'));

    rcoLegacy.setPurchaseFailCallback(function (o) {
        $rQuery('body').trigger('rbwc_purchase_reject', {type: 'fail'});
    });
    rcoLegacy.setPurchaseDeniedCallback(function (o) {
        $rQuery('body').trigger('rbwc_purchase_reject', {type: 'deny'});
    });
    rcoLegacy.setCustomerChangedEventCallback(function (customer) {

        resursBankRcoDataContainer.rco_customer = customer;
        resursBankRcoDataContainer.rco_payment = customer.paymentMethod;
        $rQuery('body').trigger('rbwc_customer_synchronize', {
            version: 1
        });
    });
    rcoLegacy.setBookingCallback(function (rcoLegacyData) {
        getResursAjaxify(
            'post',
            'resursbank_checkout_create_order',
            getResursWooCommerceCustomer(),
            function (response) {
                if (response['result'] === 'success') {
                    rcoLegacy.confirmOrder(true);
                } else {
                    if (typeof response['messages'] !== "undefined") {
                        setRbwcGenericError(response['messages'])
                    }
                }
            }
        )
        return false;
    });
}
