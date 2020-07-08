var $rQuery = jQuery.noConflict();

/**
 * Ajaxify plugin internal calls.
 * @param requestMethod
 * @param requestVerb
 * @param requestData
 * @param callbackMethod
 */
function getResursAjaxify(requestMethod, requestVerb, requestData, callbackMethod) {
    if (typeof requestData === 'object') {
        if (typeof requestData['action'] === 'undefined') {
            requestData['action'] = requestVerb;
        }
        if (typeof requestData['n'] === 'undefined' || requestData['n'] === '') {
            requestData['n'] = getResursLocalization('noncify');
        }
    }
    $rQuery.ajax(
        {
            type: requestMethod,
            url: getResursLocalization('ajaxify'),
            data: requestData
        }
    ).done(
        function (data, textStatus, jqXhr) {
            if (data['ajax_success']) {
                callbackMethod(data, textStatus, jqXhr)
            } else {
                getResursError(data);
            }
        }
    );
}

/**
 * @param data
 */
function getResursError(data) {
    console.log("Resurs Bank Ajax Error: ", data);
}

/**
 *
 * @param element
 */
function getResursSpin(element) {
    $rQuery(element).html(
        $rQuery('<img>', {
            'src': getResursLocalization('spin')
        })
    );
}
