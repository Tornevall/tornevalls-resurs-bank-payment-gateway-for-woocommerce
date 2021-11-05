var $rQuery = jQuery.noConflict();
var resursGetAddressCustomerType;
var resursHasPlaceOrder;
var resursTemporaryCartTotal = 0.00;

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
 * RCO Legacy variable to define allowed communication source.
 * @type {string}
 * @since 0.0.1.0
 */
var RESURSCHECKOUT_IFRAME_URL = '';

/**
 * Logging.
 * @param consEntry
 * @since 0.0.1.0
 */
function trbwcLog(consEntry) {
    console.log('[trbwc] ' + consEntry);
}

/**
 * Ajaxify plugin internal calls.
 * @param requestMethod
 * @param requestVerb
 * @param requestData
 * @param callbackMethod
 * @since 0.0.1.0
 */
function getResursAjaxify(requestMethod, requestVerb, requestData, callbackMethod) {
    var failMethod = null;
    if (typeof arguments[4] !== 'undefined') {
        failMethod = arguments[4];
    }
    if (typeof requestData === 'object') {
        if (typeof requestData['action'] === 'undefined') {
            requestData['action'] = requestVerb;
        }
        if (typeof requestData['n'] === 'undefined' || requestData['n'] === '' || requestData['n'] === true) {
            requestData['n'] = getResursLocalization('noncify');
        }
    }
    $rQuery.ajax(
        {
            type: requestMethod,
            url: getResursLocalization('ajaxify'),
            data: requestData,
            timeout: parseInt(getResursLocalization('ajaxifyTimeout'))
        }
    ).done(
        function (data, textStatus, jqXhr) {
            if (data['ajax_success']) {
                callbackMethod(data, textStatus, jqXhr)
            } else {
                if (typeof failMethod === 'function') {
                    console.log(
                        typeof data['error'] !== 'undefined' ? data['error'] : 'Error found without error message.'
                    );
                    failMethod(data, textStatus, jqXhr);
                } else {
                    getResursError(data);
                }
            }
        }
    ).fail(
        function (data, textStatus, jqXhr) {
            if (typeof failMethod === 'function') {
                console.dir(data);
                failMethod(data, typeof data.statusText !== 'undefined' ? data.statusText : textStatus, jqXhr);
                return;
            } else {
                callbackMethod(data, typeof data.statusText !== 'undefined' ? data.statusText : textStatus, jqXhr);
            }
            getResursError(typeof data.statusText !== 'undefined' ? data.statusText : textStatus);
        }
    );
}

/**
 * @param data
 * @since 0.0.1.0
 */
function getResursError(data) {
    var useVisibleElement;
    if (typeof arguments[1] !== 'undefined') {
        if ($rQuery(arguments[1]).length > 0) {
            useVisibleElement = $rQuery(arguments[1]);
            useVisibleElement.html(data['error']);
            if (typeof data['error'] !== 'undefined' && data['error'] === 'nonce_validation') {
                useVisibleElement.html(getResursLocalization('nonce_error'));
                return;
            }
        }
    } else {
        console.log('RBWC Ajax Backend Error: ', data);
    }

    if (typeof data['error'] !== 'undefined' && data['error'] === 'nonce_validation') {
        alert(getResursLocalization('nonce_error'));
    }
}

/**
 * @param element
 * @since 0.0.1.0
 */
function getResursSpin(element) {
    $rQuery(element).html(
        $rQuery('<img>', {
            'src': getResursLocalization('spin')
        })
    );
}

/**
 * Display errors for this plugin.
 * @param errorMessage
 * @since 0.0.1.0
 */
function setRbwcGenericError(errorMessage) {
    var checkoutForm = $rQuery('form.checkout');
    if (checkoutForm.length > 0) {
        $rQuery('.woocommerce-error').remove();
        $rQuery('.woocommerce-message').remove();
        checkoutForm.prepend(
            $rQuery('<div>', {class: 'woocommerce-error'}).html(errorMessage)
        );

        $rQuery('html, body').animate({
            scrollTop: ($rQuery('.woocommerce').offset().top - 100)
        }, 1000);
    } else {
        console.log(errorMessage);
    }
}

/**
 * @since 0.0.1.0
 */
function resursPlaceOrderControl() {
    if (typeof resursTemporaryCartTotal !== 'undefined' && getResursLocalization('checkoutType') === 'rco') {
        if ($rQuery('#place_order').length > 0) {
            resursHasPlaceOrder = true;
            if ($rQuery('#place_order').is(':visible') && resursTemporaryCartTotal === 0) {
                trbwcLog('Button for placing order is visible and cart control indicates a price of 0.');
                $rQuery('#resursbank_rco_container').hide();
                $rQuery('.woocommerce-billing-fields').show();
                $rQuery('.woocommerce-shipping-fields').show();
            }
            return;
        }
        if ($rQuery('#place_order').length === 0 && resursTemporaryCartTotal > 0) {
            trbwcLog('Cart control indicates change to ' + resursTemporaryCartTotal + '.');
            resursHasPlaceOrder = false;
            $rQuery('.woocommerce-billing-fields').hide();
            $rQuery('.woocommerce-shipping-fields').hide();
            $rQuery('#resursbank_rco_container').show();
        }
    }
}

jQuery(document).ready(function () {
    jQuery('.variations_form').each(function () {
        jQuery(this).on('found_variation', function (event, variation) {
            var price = variation.display_price;
            getResursAjaxify(
                'post',
                'resursbank_get_new_annuity_calculation',
                {
                    'price': price
                },
                function (pricedata) {
                    if (typeof pricedata.price !== 'undefined') {
                        $rQuery('#r_annuity_price').html(pricedata.price);
                    }
                }
            );
        });
    });
});


/**
 * Render cost of purchase popup.
 * @param method
 * @param total
 * @since 0.0.1.0
 */
function getRbReadMoreClicker(method, total) {
    var costOfPurchaseUrl = getResursLocalization('ajaxify');
    var costOfPurchaseVars = '?action=resursbank_get_cost_of_purchase&method=' + method + '&total=' + total;
    //console.log(costOfPurchaseUrl+costOfPurchaseVars);
    window.open(
        costOfPurchaseUrl + costOfPurchaseVars,
        'costOfPurchasePopup',
        'toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,copyhistory=no,resizable=yes,width=650px,height=740px'
    )
}
