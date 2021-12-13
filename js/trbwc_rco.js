// This script should not be loaded unless there are errors  in Resurs Checkout.

jQuery(document).ready(function () {
    rcoCheck();
});

/**
 * If this script has been loaded, this section should be executed.
 */
function rcoCheck() {
    if (typeof trbwc_rco !== 'undefined' && typeof trbwc_rco.exception !== 'undefined') {
        trbwcLog('An internal error occurred: ' + trbwc_rco.exception.message + ' (code ' + trbwc_rco.exception.code + ')');
        setRbwcGenericError(
            'An internal error occurred: ' +
            trbwc_rco.exception.message +
            ' (code ' + trbwc_rco.exception.code + ')'
        );
    }
}
