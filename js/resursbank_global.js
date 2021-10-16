var $rQuery = jQuery.noConflict();
var resursGetAddressCustomerType;

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
                getResursError(data);
                if (typeof failMethod === 'function') {
                    failMethod(data, textStatus, jqXhr);
                }
            }
        }
    ).fail(
        function (data, textStatus, jqXhr) {
            if (typeof failMethod === 'function') {
                failMethod(data, textStatus, jqXhr);
            } else {
                callbackMethod(data, textStatus, jqXhr);
            }
            getResursError(data.statusText);
        }
    );
}

/**
 * @param data
 * @since 0.0.1.0
 */
function getResursError(data) {
    if (typeof data['error'] !== 'undefined') {
        alert(getResursLocalization('nonce_error'));
    }
    console.log("RBWC Ajax Backend Error: ", data);
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
