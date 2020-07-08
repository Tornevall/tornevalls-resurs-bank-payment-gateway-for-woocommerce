$rQuery(document).ready(function ($) {
    getResursAdminFields();
});

/**
 * Handle wp-admin, and update realtime fields.
 */
function getResursAdminFields() {
    getResursAdminCheckoutType();
    getResursAdminPasswordButton();
}

function getResursAdminPasswordButton() {
    var pwBox = $rQuery('#trbwc_admin_password');
    if (pwBox.length > 0) {
        pwBox.after(
            $rQuery(
                '<button>',
                {
                    'type': 'button',
                    'style': 'margin-left: 5px;',
                    'onclick': 'getResursCredentialsResult()'
                }
            ).html(getResursLocalization('resurs_test_credentials'))
        );
        pwBox.parent().children('.description').before(
            $rQuery(
                '<div>',
                {
                    'id': 'resurs_test_credentials_result',
                    'style': 'margin-top: 3px; padding 5px; width: 400px;'
                }
            )
        );
    }
}

/**
 * Backend-test chosen credentials.
 */
function getResursCredentialsResult() {
    if ($rQuery('#trbwc_admin_password').length > 0) {
        getResursSpin('#resurs_test_credentials_result');
        var uData = {
            'p': $rQuery('#trbwc_admin_password').val(),
            'u': $rQuery('#trbwc_admin_login').val(),
            'e': $rQuery('#trbwc_admin_environment').val()
        };
        getResursAjaxify('post', 'resursbank_test_credentials', uData, function (data) {
            if (data['validation']) {
                $rQuery('#resurs_test_credentials_result').html(getResursLocalization('credential_success_notice'))
            } else {
                $rQuery('#resurs_test_credentials_result').html(getResursLocalization('credential_failure_notice'))
            }
        });
    }
}

/**
 * Update description of checkout type to the selected.
 */
function getResursAdminCheckoutType() {
    var checkoutType = $rQuery('#trbwc_admin_checkout_type');
    if (checkoutType.length > 0) {
        $rQuery('#trbwc_admin_checkout_type').parent().children('.description').html(
            getResursLocalization('translate_checkout_' + checkoutType.val())
        );
    }
}

/**
 * @param current
 */
function resursUpdateFlowDescription(current) {
    $rQuery('#trbwc_admin_checkout_type').parent().children('.description').html(
        getResursLocalization('translate_checkout_' + current.value)
    );
}

/**
 * @param key
 * @returns {boolean}
 */
function getResursLocalization(key) {
    var returnValue = false;
    if (typeof l_trbwc_resursbank_admin[key] !== 'undefined') {
        returnValue = l_trbwc_resursbank_admin[key]
    } else if (typeof l_trbwc_resursbank_all[key] !== 'undefined') {
        returnValue = l_trbwc_resursbank_all[key];
    }
    return returnValue;
}
