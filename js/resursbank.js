$rQuery(document).ready(function ($) {
});

/**
 * @param key
 * @returns {boolean}
 * @since 0.0.1.0
 */
function getResursLocalization(key) {
    var returnValue = false;
    if (typeof l_trbwc_resursbank_all[key] !== 'undefined') {
        returnValue = l_trbwc_resursbank_all[key];
    } else if (typeof l_trbwc_resursbank[key] !== 'undefined') {
        returnValue = l_trbwc_resursbank[key];
    }
    return returnValue;
}
