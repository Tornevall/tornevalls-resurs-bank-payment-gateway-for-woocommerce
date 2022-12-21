var $rQuery = jQuery.noConflict();
var resursGetAddressCustomerType;
var resursHasPlaceOrder;
var resursTemporaryCartTotal = 0.00;

/**
 * Logging.
 * @param consEntry
 * @since 0.0.1.0
 */
function trbwcLog(consEntry) {
    console.log('[' + getResursLocalization('prefix') + '] ' + consEntry);
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
            timeout: parseInt(getResursLocalization('ajaxifyTimeout')) + 1
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
                failMethod(data, typeof data.statusText !== 'undefined' ? data.statusText : textStatus, jqXhr);
                return;
            } else {
                callbackMethod(data, typeof data.statusText !== 'undefined' ? data.statusText : textStatus, jqXhr);
            }
            getResursError(
                typeof data.statusText !== 'undefined' ? data.statusText : textStatus,
                null,
                requestVerb
            );
        }
    );
}

/**
 * @param data
 * @since 0.0.1.0
 */
function getResursError(data) {
    var requestVerb = typeof arguments[2] !== 'undefined' ? arguments[2] : '';

    if (typeof arguments[1] !== 'undefined') {
        var isWarningElement = $rQuery(arguments[1]);
        if (isWarningElement.length > 0) {
            return rbwcShowErrorElement(data, isWarningElement, requestVerb);
        } else {
            return rbwcShowErrorElement(data, null, requestVerb);
        }
    } else {
        if (data !== 'timeout') {
            return rbwcShowErrorElement(
                'RBWC Ajax Backend Error: ' + data,
                null,
                requestVerb
            );
        }
        console.log('RBWC Ajax Backend Error: ' + data);
    }
}

/**
 * errorElement must be of type jquery-extracted.
 * @param data
 * @param errorElement
 */
function rbwcShowErrorElement(data, errorElement, requestVerb) {
    var reqVerb = requestVerb !== null ? ' (Request: ' + requestVerb + ')' : '';

    if (typeof data === 'string') {
        console.log('RBWC Ajax Backend Error: ', data);
    }
    if (null !== errorElement) {
        if (typeof data['error'] !== 'undefined') {
            errorElement.html(data['error']);
        } else if (typeof data === 'string') {
            errorElement.html(data);
        }
    } else if (typeof data === 'string') {
        if (data !== '[object Object]' && data !== 'timeout') {
            // Only scream errors when they are human understandable.
            alert(data + ' ' + reqVerb);
        }
        console.log(data);
    }
    if (typeof data['error'] !== 'undefined' && data['error'] === 'nonce_validation') {
        if (null !== errorElement) {
            errorElement.html(getResursLocalization('nonce_error'));
        }
    }
    if (typeof data.statusText !== 'undefined' && data.statusText === 'timeout') {
        alert('Timeout error. Please try again.');
    }
    trbwcLog('ErrorLog (rbwcShowErrorElement):');
    console.dir(data);
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
