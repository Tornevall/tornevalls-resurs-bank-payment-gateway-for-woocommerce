/**
 * @since 0.0.1.0
 */
$rQuery(document).ready(function ($) {
    getResursAdminFields();
    setTimeout('getCallbackMatches()', 5000);
});

var resursCallbackActiveTime = 0;
var resursCallbackActiveInterval = 2;
var resursCallbackActiveTimeout = 15;
var resursCallbackTestHandle;
var resursCallbackReceiveSuccess = false;
var resursEnvironment;

/**
 * Handle wp-admin, and update realtime fields.
 * @since 0.0.1.0
 */
function getResursAdminFields() {
    getResursConfigPopupPrevention(
        [
            '#trbwc_admin_environment',
            '#trbwc_admin_login',
            '#trbwc_admin_password',
            '#trbwc_admin_login_production',
            '#trbwc_admin_password_production',
            'select[id*=r_annuity_select]'
        ]
    );
    getResursEnvironmentFields();
    getResursAdminCheckoutType();
    getResursAdminPasswordButton();
}

/**
 * Disable popup warnings about config changes for all elements added here.
 * @param elements
 * @since 0.0.1.0
 */
function getResursConfigPopupPrevention(elements) {
    for (var i = 0; i < elements.length; i++) {
        $rQuery(elements[i]).click(function (e) {
            window.onbeforeunload = null;
            e.preventDefault();
            getResursEnvironmentFields();
        });
    }
}

/**
 * @since 0.0.1.0
 */
function getResursEnvironmentFields() {
    resursEnvironment = $rQuery('#trbwc_admin_environment').find(':selected').val();
    switch (resursEnvironment) {
        case 'test':
            $rQuery('#trbwc_admin_login_production').parent().parent().hide();
            $rQuery('#trbwc_admin_password_production').parent().parent().hide();
            $rQuery('#trbwc_admin_login').parent().parent().fadeIn();
            $rQuery('#trbwc_admin_password').parent().parent().fadeIn();
            break;
        case 'live':
            $rQuery('#trbwc_admin_login_production').parent().parent().fadeIn();
            $rQuery('#trbwc_admin_password_production').parent().parent().fadeIn();
            $rQuery('#trbwc_admin_login').parent().parent().hide();
            $rQuery('#trbwc_admin_password').parent().parent().hide();
            break;
        default:
    }
}

/**
 * Make sure we have data up-to-date.
 * @since 0.0.1.0
 */
function getCallbackMatches() {
    getResursAjaxify(
        'GET',
        'resursbank_get_callback_matches',
        {
            'n': true,
            't': getResursLocalization('current_tab')
        },
        function (data) {
            if (typeof data['errors'] !== 'undefined' &&
                parseInt(data['errors']['code']) > 0
            ) {
                if ($rQuery('#resurs_credentials_test_username_box').length > 0) {
                    if (resursEnvironment === 'test') {
                        $rQuery('#resurs_credentials_test_username_box').css('font-weight', 'bold');
                        $rQuery('#resurs_credentials_test_username_box').css('color', '#990000');
                        $rQuery('#resurs_credentials_test_username_box').html(data['errors']['message']);
                    } else {
                        $rQuery('#resurs_credentials_production_username_box').css('font-weight', 'bold');
                        $rQuery('#resurs_credentials_production_username_box').css('color', '#990000');
                        $rQuery('#resurs_credentials_production_username_box').html(data['errors']['message']);
                    }
                }
            }
            if (typeof data['requireRefresh'] !== "undefined" && data['requireRefresh'] === true) {
                var canUpdate = confirm(getResursLocalization('update_callbacks_required'));
                if (canUpdate) {
                    getResursAjaxify('post', 'get_internal_resynch', {'n': true}, function () {
                        if ($rQuery('#button_trbwc_admin_payment_methods_button').length > 0) {
                            $rQuery('#div_trbwc_admin_payment_methods_button').html(
                                $rQuery('<div>', {
                                    'style': 'font-weight: bold; color: #000099;'
                                }).html(getResursLocalization('reloading'))
                            );
                            $rQuery('#div_trbwc_admin_callbacks_button').html(
                                $rQuery('<div>', {
                                    'style': 'font-weight: bold; color: #000099;'
                                }).html(getResursLocalization('reloading'))
                            );
                            document.location.reload();
                        } else {
                            alert(getResursLocalization('update_callbacks_refresh'));
                        }
                    });
                }
            }
        }
    )
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
    getResursAjaxify('post', 'resursbank_get_payment_methods', {'n': true}, function (data) {
        console.log(data['reload']);
        if (data['reload'] === true) {
            $rQuery('#div_trbwc_admin_payment_methods_button').html(
                $rQuery('<div>', {
                    'style': 'font-weight: bold; color: #000099;'
                }).html(getResursLocalization('reloading'))
            );
            document.location.reload();
        } else if (data['error'] !== '') {
            getResursError(data['error'], '#div_trbwc_admin_payment_methods_button');
        } else {
            getResursError('Unable to update.', '#div_trbwc_admin_payment_methods_button');
        }
    }, function (d) {
        getResursError(d, '#div_trbwc_admin_payment_methods_button');
    });
}

/**
 * @since 0.0.1.0
 */
function getResursCallbacks() {
    getResursSpin('#div_trbwc_admin_callbacks_button');
    getResursAjaxify('post', 'resursbank_get_new_callbacks', {'n': ''}, function (data) {
        if (data['reload'] === true) {
            $rQuery('#div_trbwc_admin_callbacks_button').html(
                $rQuery('<div>', {
                    'style': 'font-weight: bold; color: #000099;'
                }).html(getResursLocalization('reloading'))
            );
            document.location.reload();
        } else if (data['error'] !== '') {
            getResursError(data['error'], '#div_trbwc_admin_payment_methods_button');
        } else {
            getResursError('Unable to update.', '#div_trbwc_admin_callbacks_button');
        }
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
function getResursCredentialsTestForm(pwBox, pwBoxId) {
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

    var pwBoxResultName = pwBoxId + '_result';

    pwBox.parent().children('.description').before(
        $rQuery(
            '<div>',
            {
                'id': pwBoxResultName,
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
    // This box became too big so functions are split up.
    if ($rQuery('#trbwc_admin_password').length > 0) {
        // One time nonce controlled credential importer.
        getDeprecatedCredentialsForm();
        getResursCredentialsTestForm($rQuery('#trbwc_admin_password'), 'trbwc_admin_password');
        getResursCredentialsTestForm($rQuery('#trbwc_admin_password_production'), 'trbwc_admin_password_production');
        getResursCredentialDivs();
    }
}

/**
 * @since 0.0.1.0
 */
function getResursCredentialDivs() {
    // Create an empty div here for credentials stuff.
    $rQuery('#trbwc_admin_login').parent().children('.description').before(
        $rQuery(
            '<div>',
            {
                'id': 'resurs_credentials_test_username_box'
            }
        )
    );
    $rQuery('#trbwc_admin_login_production').parent().children('.description').before(
        $rQuery(
            '<div>',
            {
                'id': 'resurs_credentials_production_username_box'
            }
        )
    );

}

/**
 * Restore the removal button after spinner.
 * @param cbid
 * @since 0.0.1.0
 */
function getCallbackButtonRestored(cbid) {
    $rQuery('#remove_cb_btn_' + cbid).html($rQuery('<button>', {
        type: 'button',
        click: function () {
            doResursRemoveCallback(cbid)
        }
    }).html('X'));
}

/**
 * unregisterEventCallback
 * @param cbid
 * @since 0.0.1.0
 */
function doResursRemoveCallback(cbid) {
    if (confirm(getResursLocalization('remove_callback_confirm') + ' ' + cbid + '?')) {
        getResursSpin('#remove_cb_btn_' + cbid);

        getResursAjaxify(
            'post',
            'resursbank_callback_unregister',
            {
                'callback': cbid,
                'n': true
            },
            function (data) {
                if (typeof data['unreg'] !== 'undefined' &&
                    data['unreg'] === true &&
                    data['callback'] !== ''
                ) {
                    $rQuery('#callback_row_' + data['callback']).hide('medium');
                } else {
                    getCallbackButtonRestored(data['callback']);
                    // There might be a denial here that needs to be alerted.
                    if (data['message'] !== '') {
                        alert(data['message']);
                    }
                }
            }
        );
    }
}

/**
 * registerEventCallback
 * @param cbid
 * @since 0.0.1.0
 * @deprecated Not in use.
 */
function doResursUpdateCallback(cbid) {
    //alert("Update " + cbid);
}

/**
 * Handle credentials from legacy versions.
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
    var apiLoginBox;
    var apiPwBox;
    var resultBox;

    switch (resursEnvironment) {
        case 'live':
            apiLoginBox = '#trbwc_admin_login_production';
            apiPwBox = '#trbwc_admin_password_production';
            resultBox = '#trbwc_admin_password_production_result';
            break;
        default:
            apiLoginBox = '#trbwc_admin_login';
            apiPwBox = '#trbwc_admin_password';
            resultBox = '#trbwc_admin_password_result';
    }

    if ($rQuery('#trbwc_admin_password').length > 0) {
        getResursSpin(resultBox);
        var uData = {
            'p': $rQuery(apiPwBox).val(),
            'u': $rQuery(apiLoginBox).val(),
            'e': $rQuery('#trbwc_admin_environment').val()
        };
        getResursAjaxify(
            'post',
            'resursbank_test_credentials',
            uData,
            function (data) {
                if (data['validation']) {
                    $rQuery(resultBox).html(getResursLocalization('credential_success_notice'))
                } else {
                    var noValidation = getResursLocalization('credential_failure_notice');
                    if (typeof data['statusText'] === 'string') {
                        noValidation += ' (Status: ' + data['statusText'] + ')';
                    }
                    $rQuery(resultBox).html(
                        noValidation
                    );
                }
            },
            function (error) {
                getResursError(error, resultBox);
            }
        );
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
    } else if (typeof l_trbwc_resursbank_order !== 'undefined' &&
        typeof l_trbwc_resursbank_order[key] !== 'undefined'
    ) {
        // Order localization box is not always present.
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

/**
 * Find out whether chosen annuity element is disabled or not.
 * @param currentAnnuityField
 * @returns {boolean}
 * @since 0.0.1.0
 */
function isResursAnnuityDisabled(currentAnnuityField) {
    return currentAnnuityField.attr('class').indexOf('r_annuity_disabled') > -1 ? true : false;
}

/**
 * Select a new annuity factor.
 * @param id
 * @since 0.0.1.0
 */
function setResursAnnuityClick(o, id, action) {
    var currentAnnuityField = $rQuery('#r_annuity_field_' + id);
    var selectAnnuity = $rQuery('#r_annuity_select_' + id);
    if (isResursAnnuityDisabled(currentAnnuityField)) {
        var mode = 'e';
    } else {
        var mode = 'd';
    }

    if (action === 'set') {
        if (mode === 'd') {
            mode = 'e';
        }
    }

    getResursSpin('#r_activity_' + id);
    getResursAjaxify(
        'post',
        'resursbank_set_new_annuity',
        {
            'n': '',
            'id': id,
            'duration': selectAnnuity.find(':selected').val(),
            'mode': mode
        },
        function (data) {
            if (typeof data['mode'] !== "undefined") {
                var annuityElement = $rQuery('#r_annuity_field_' + data['id']);
                var annuityButton = $rQuery('#r_annuity_button_' + data['id']);
                if (data['mode'] === 'd') {
                    annuityElement.addClass('r_annuity_disabled');
                    annuityElement.removeClass('r_annuity_enabled');
                    annuityButton.text(getResursLocalization('enable'));
                } else {
                    annuityElement.removeClass('r_annuity_disabled');
                    annuityElement.addClass('r_annuity_enabled');
                    annuityButton.text(getResursLocalization('disable'));

                    $rQuery('select[id^=r_annuity_select_]').parent().parent().each(function (p, i) {
                        if (i.id !== "r_annuity_field_" + id) {
                            $rQuery(i).removeClass('r_annuity_enabled');
                            $rQuery(i).addClass('r_annuity_disabled');
                            $rQuery(i).children('button').text(getResursLocalization('enable'));
                        }
                    });
                }
                $rQuery('#r_activity_' + id).html('');
            }
        }
    );
}

/**
 * @since 0.0.1.0
 */
function resursToggleMetaData() {
    $rQuery('.resurs_order_meta_container').toggle('medium');
    $rQuery('#resurs_meta_expand_button').toggle();
}
