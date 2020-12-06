/**
 * @since 0.0.1.0
 */
$rQuery(document).ready(function ($) {
    getResursAdminFields();
});

var resursCallbackActiveTime = 0;
var resursCallbackActiveInterval = 2;
var resursCallbackActiveTimeout = 15;
var resursCallbackTestHandle;
var resursCallbackReceiveSuccess = false;

/**
 * Handle wp-admin, and update realtime fields.
 * @since 0.0.1.0
 */
function getResursAdminFields() {
    getResursAdminCheckoutType();
    getResursAdminPasswordButton();
}

/**
 * @since 0.0.1.0
 */
function getDeprecatedCredentialsForm() {
    if (getResursLocalization('deprecated_login')) {
        var userBox = $rQuery('#trbwc_admin_login');
        userBox.after(
            $rQuery(
                '<button>',
                {
                    'type': 'button',
                    'style': 'margin-left: 5px;',
                    'onclick': 'getResursDeprecatedLogin()'
                }
            ).html(getResursLocalization('resurs_deprecated_credentials'))
        );
        userBox.parent().children('.description').before(
            $rQuery(
                '<div>',
                {
                    'id': 'resurs_import_credentials_result',
                    'style': 'margin-top: 3px; padding 5px; width: 400px; ' +
                        'font-style: italic; font-weight: bold; color: #000099;'
                }
            )
        );
    }
}

/**
 * @since 0.0.1.0
 */
function getResursPaymentMethods() {
    getResursSpin('#div_trbwc_admin_payment_methods_button');
    getResursAjaxify('post', 'resursbank_get_payment_methods', {}, function () {
        $rQuery('#div_trbwc_admin_payment_methods_button').html(
            $rQuery('<div>', {
                'style': 'font-weight: bold; color: #000099;'
            }).html(getResursLocalization('reloading'))
        );
        document.location.reload();
    });
}

/**
 * @since 0.0.1.0
 */
function getResursCallbacks() {
    getResursSpin('#div_trbwc_admin_callbacks_button');
    getResursAjaxify('post', 'resursbank_get_new_callbacks', {}, function () {
        $rQuery('#div_trbwc_admin_callbacks_button').html(
            $rQuery('<div>', {
                'style': 'font-weight: bold; color: #000099;'
            }).html(getResursLocalization('reloading'))
        );
        document.location.reload();
    });
}

/**
 * @since 0.0.1.0
 */
function getResursCallbackTest() {
    getResursSpin('#div_trbwc_admin_trigger_callback_button');
    getResursAjaxify('post', 'resursbank_get_trigger_test', {}, function (response) {
        $rQuery('#div_trbwc_admin_trigger_callback_button').html(
            $rQuery('<div>', {
                'style': 'font-weight: bold; color: #000099;'
            }).html(response['html'])
        );
        var testElement = $rQuery('#resursWaitingForTest');
        if (testElement.length > 0) {
            resursCallbackReceiveSuccess = false;
            testElement.html(getResursLocalization('waiting_for_callback'));
            resursCallbackTestHandle = setInterval(getResursCallbackResponse, resursCallbackActiveInterval * 1000);
            getResursCallbackResponse();
        }
    });
}

/**
 * Wait for callback (TEST) data response.
 * @since 0.0.1.0
 */
function getResursCallbackAnalyze() {
    getResursAjaxify('post', 'resursbank_get_trigger_response', {"runTime": resursCallbackActiveTime}, function (response) {
        var replyString;
        if (typeof response['lastResponse'] !== 'undefined') {
            replyString = response['lastResponse'];
        } else {
            replyString = getResursLocalization('trigger_test_fail');
        }
        $rQuery('#resursWaitingForTest').html(replyString);
        if (typeof response['success'] !== 'undefined') {
            resursCallbackReceiveSuccess = true;
        }
    });
}

/**
 * Looking for callback test.
 * @since 0.0.1.0
 */
function getResursCallbackResponse() {
    resursCallbackActiveTime = resursCallbackActiveTime + resursCallbackActiveInterval;
    console.log('getResursCallbackResponse waited ' +
        'for ' + resursCallbackActiveTime + '/' + resursCallbackActiveTimeout + ' seconds.');

    if (resursCallbackActiveTime >= resursCallbackActiveTimeout) {
        console.log('Wait for received callback cancelled after callback timeout.');
        clearInterval(resursCallbackTestHandle);
        $rQuery('#resursWaitingForTest').html(
            getResursLocalization('callback_test_timeout') + ' ' + resursCallbackActiveTime + 's.'
        );
        resursCallbackActiveTime = 0;
        return;
    }

    getResursCallbackAnalyze();

    // Break after second run.
    if (resursCallbackReceiveSuccess) {
        console.log('Wait for received callback cancelled after success.');
        clearInterval(resursCallbackTestHandle);
        resursCallbackActiveTime = 0;
        return;
    }
}

/**
 * @param pwBox
 * @since 0.0.1.0
 */
function getResursCredentialsTestForm(pwBox) {
    var pwButton = $rQuery(
        '<button>',
        {
            'type': 'button',
            'style': 'margin-left: 5px;',
            'onclick': 'getResursCredentialsResult()'
        }
    ).html(getResursLocalization('resurs_test_credentials'));

    pwBox.after(
        pwButton
    );

    pwBox.parent().children('.description').before(
        $rQuery(
            '<div>',
            {
                'id': 'resurs_test_credentials_result',
                'style': 'margin-top: 3px; padding 5px; width: 400px; ' +
                    'font-style: italic; font-weight: bold; color: #000099;'
            }
        )
    );
}

/**
 * @since 0.0.1.0
 */
function getResursAdminPasswordButton() {
    var pwBox = $rQuery('#trbwc_admin_password');
    // This box became too big so functions are split up.
    if (pwBox.length > 0) {
        // One time nonce controlled credential importer.
        getDeprecatedCredentialsForm();
        getResursCredentialsTestForm(pwBox);
    }
}

/**
 * unregisterEventCallback
 * @param cbid
 * @since 0.0.1.0
 */
function doResursRemoveCallback(cbid) {
    alert("Remove " + cbid);
}

/**
 * registerEventCallback
 * @param cbid
 * @since 0.0.1.0
 */
function doResursUpdateCallback(cbid) {
    alert("Update " + cbid);
}

/**
 * @since 0.0.1.0
 */
function getResursDeprecatedLogin() {
    if ($rQuery('#trbwc_admin_password').length > 0) {
        getResursSpin('#resurs_import_credentials_result');
        getResursAjaxify('post', 'resursbank_import_credentials', {}, function (data) {
            if (data['login'] !== '' && data['pass'] !== '') {
                $rQuery('#resurs_import_credentials_result').html(getResursLocalization('credential_import_success'));
                $rQuery('#trbwc_admin_login').val(data['login']);
                $rQuery('#trbwc_admin_password').val(data['pass']);
                getResursCredentialsResult();
            } else {
                $rQuery('#resurs_import_credentials_result').html(getResursLocalization('credential_import_failed'));
            }
        });
    }
}

/**
 * Backend-test chosen credentials.
 * @since 0.0.1.0
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
                var noValidation = getResursLocalization('credential_failure_notice');
                if (typeof data['statusText'] === 'string') {
                    noValidation += ' (Status: ' + data['statusText'] + ')';
                }
                $rQuery('#resurs_test_credentials_result').html(
                    noValidation
                )
            }
        });
    }
}

/**
 * Update description of checkout type to the selected.
 * @since 0.0.1.0
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
 * @since 0.0.1.0
 */
function resursUpdateFlowDescription(current) {
    $rQuery('#trbwc_admin_checkout_type').parent().children('.description').html(
        getResursLocalization('translate_checkout_' + current.value)
    );
}

/**
 * @param key
 * @returns {boolean}
 * @since 0.0.1.0
 */
function getResursLocalization(key) {
    var returnValue = false;
    if (typeof l_trbwc_resursbank_admin[key] !== 'undefined') {
        returnValue = l_trbwc_resursbank_admin[key]
    } else if (typeof l_trbwc_resursbank_all[key] !== 'undefined') {
        returnValue = l_trbwc_resursbank_all[key];
    } else if (typeof l_trbwc_resursbank_order[key] !== 'undefined') {
        returnValue = l_trbwc_resursbank_order[key];
    }
    return returnValue;
}

/**
 * @since 0.0.1.0
 */
function setResursFraudControl() {
    $rQuery('#trbwc_admin_waitForFraudControl').attr('checked', 'checked');
    if ($rQuery('.waitForFraudControlWarning').length === 0) {
        $rQuery('#trbwc_admin_annulIfFrozen').parent().after(
            $rQuery(
                '<div>',
                {
                    'class': 'waitForFraudControlWarning',
                }
            ).html(getResursLocalization('requireFraudControl'))
        );
    }
}

/**
 * @since 0.0.1.0
 */
function getResursFraudFlags(clickObject) {
    if ($rQuery('#trbwc_admin_waitForFraudControl').length) {
        var fraudSettings = {
            'waitForFraudControl': $rQuery('#trbwc_admin_waitForFraudControl').is(':checked'),
            'annulIfFrozen': $rQuery('#trbwc_admin_annulIfFrozen').is(':checked'),
            'finalizeIfBooked': $rQuery('#trbwc_admin_finalizeIfBooked').is(':checked'),
        };

        if (!fraudSettings['annulIfFrozen']) {
            $rQuery('.waitForFraudControlWarning').remove();
        }

        // Add messge: 'string' to below ruleset to activate an alery.
        var prohibitRuleset = {
            'notAlone': {
                'waitForFraudControl': false,
                'annulIfFrozen': true,
            }
        };
        var prohibitActions = {
            'notAlone': function () {
                setResursFraudControl();
            }
        }

        for (var prohibitId in prohibitRuleset) {
            var matches = 0;
            var requireMatches = 0;
            for (var prohibitKey in prohibitRuleset[prohibitId]) {
                requireMatches++;
                if (fraudSettings[prohibitKey] === prohibitRuleset[prohibitId][prohibitKey]) {
                    matches++;
                }
            }

            if (matches === requireMatches) {
                if (typeof prohibitActions[prohibitId] !== 'undefined' &&
                    prohibitActions[prohibitId] !== ''
                ) {
                    prohibitActions[prohibitId]();
                }
            }
        }
    }
}
