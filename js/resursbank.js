$rQuery(document).ready(function ($) {
});

/**
 * @param key
 * @returns {boolean}
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
