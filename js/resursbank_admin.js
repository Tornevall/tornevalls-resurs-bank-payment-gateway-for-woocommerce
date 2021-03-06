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
var resursJustImported = false;
var resursPluginPrefix = getResursLocalization('prefix');

/**
 * Handle wp-admin, and update realtime fields.
 * @since 0.0.1.0
 */
function getResursAdminFields() {
    getResursConfigPopupPrevention(
        [
            '#' + resursPluginPrefix + '_admin_environment',
            '#' + resursPluginPrefix + '_admin_login',
            '#' + resursPluginPrefix + '_admin_password',
            '#' + resursPluginPrefix + '_admin_login_production',
            '#' + resursPluginPrefix + '_admin_password_production',
            'select[id*=r_annuity_select]',
            'input[id*=method_description]',
            'input[id*=method_fee]',
            'input[id^=rbenabled_]'
        ]
    );
    getResursEnvironmentFields();
    getResursAdminCheckoutType();
    getResursAdminPasswordButton();
    rbwcAdminNetworkLookup();
}

/**
 * is_admin network lookups for Resurs Bank whitelisting help.
 * @since 0.0.1.1
 */
function rbwcAdminNetworkLookup() {
    if ($rQuery('#rbwcNetworkLookup').length > 0) {
        getResursSpin('#rbwcNetworkLookup');
        getResursAjaxify(
            'get',
            'resursbank_get_network_lookup',
            {},
            function (data) {
                if (typeof data.addressRequest !== 'undefined') {
                    var displayAddressTypes = '';
                    for (var addressType in data.addressRequest) {
                        if (parseInt(addressType) > 0) {
                            displayAddressTypes += '<b>IPv' + addressType + '</b>: ' + data.addressRequest[addressType] + '<br>';
                        } else {
                            displayAddressTypes += '<b>' + addressType + '</b>: ' + data.addressRequest[addressType] + '<br>';
                        }
                    }
                    $rQuery('#rbwcNetworkLookup').html(displayAddressTypes);
                }
            }
        );
    }
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
            //e.preventDefault();
            getResursEnvironmentFields();
        });
        $rQuery(elements[i]).blur(function (e) {
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
    resursEnvironment = $rQuery('#' + resursPluginPrefix + '_admin_environment').find(':selected').val();
    switch (resursEnvironment) {
        case 'test':
            $rQuery('#' + resursPluginPrefix + '_admin_login_production').parent().parent().hide();
            $rQuery('#' + resursPluginPrefix + '_admin_password_production').parent().parent().hide();
            $rQuery('#' + resursPluginPrefix + '_admin_login').parent().parent().fadeIn();
            $rQuery('#' + resursPluginPrefix + '_admin_password').parent().parent().fadeIn();
            break;
        case 'live':
            $rQuery('#' + resursPluginPrefix + '_admin_login_production').parent().parent().fadeIn();
            $rQuery('#' + resursPluginPrefix + '_admin_password_production').parent().parent().fadeIn();
            $rQuery('#' + resursPluginPrefix + '_admin_login').parent().parent().hide();
            $rQuery('#' + resursPluginPrefix + '_admin_password').parent().parent().hide();
            break;
        default:
    }
}

/**
 * Make sure we have data up-to-date.
 * @since 0.0.1.0
 */
function getCallbackMatches() {
    if (resursJustImported) {
        trbwcLog('Callback matches will not run this round, since credentials was imported.');
        return;
    }

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
                var canUpdateAuto = parseInt(getResursLocalization('fix_callback_urls'));
                if (canUpdateAuto !== 1) {
                    var canUpdate = confirm(getResursLocalization('update_callbacks_required'));
                } else {
                    canUpdate = true;
                }
                if (canUpdate) {
                    getResursAjaxify('post', 'get_internal_resynch', {'n': true}, function () {
                        if ($rQuery('#button_' + resursPluginPrefix + '_admin_payment_methods_button').length > 0) {
                            $rQuery('#div_' + resursPluginPrefix + '_admin_payment_methods_button').html(
                                $rQuery('<div>', {
                                    'style': 'font-weight: bold; color: #000099;'
                                }).html(getResursLocalization('reloading'))
                            );
                            $rQuery('#div_' + resursPluginPrefix + '_admin_callbacks_button').html(
                                $rQuery('<div>', {
                                    'style': 'font-weight: bold; color: #000099;'
                                }).html(getResursLocalization('reloading'))
                            );
                            document.location.reload();
                        } else {
                            if (canUpdateAuto !== 1) {
                                alert(getResursLocalization('update_callbacks_refresh'));
                            }
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
    var userBox = $rQuery('#' + resursPluginPrefix + '_admin_login');
    // deprecated_login is a boolean but can is sometimes returned as "1" instead of a true value.
    var canImport = getResursLocalization('can_import_deprecated_credentials') == 1;
    if (canImport) {
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
                    'style': 'margin-top: 3px; padding 5px; width: 640px; ' +
                        'font-style: italic; font-weight: bold; color: #000099;'
                }
            )
        );
    } else if (userBox.length === 1) {
        if (getResursLocalization('deprecated_unixtime') > 0) {
            userBox.after($rQuery('<div>', {
                'id': 'resurs_import_credentials_history',
                'style': 'margin-top: 3px; padding 5px; width: 640px; ' +
                    'font-style: italic; color: #0000FF;'
            }).html(
                getResursLocalization('imported_credentials') + ' ' + getResursLocalization('deprecated_timestamp') + '.'
            ));
        }
    }
}

/**
 * @since 0.0.1.0
 */
function getResursPaymentMethods() {
    getResursSpin('#div_' + resursPluginPrefix + '_admin_payment_methods_button');
    getResursAjaxify('post', 'resursbank_get_payment_methods', {'n': true}, function (data) {
        if (data['reload'] === true) {
            $rQuery('#div_' + resursPluginPrefix + '_admin_payment_methods_button').html(
                $rQuery('<div>', {
                    'style': 'font-weight: bold; color: #000099;'
                }).html(getResursLocalization('reloading'))
            );
            document.location.reload();
        } else if (data['error'] !== '') {
            getResursError(data['error'], '#div_' + resursPluginPrefix + '_admin_payment_methods_button');
        } else {
            getResursError('Unable to update.', '#div_' + resursPluginPrefix + '_admin_payment_methods_button');
        }
    }, function (d) {
        getResursError(d, '#div_' + resursPluginPrefix + '_admin_payment_methods_button');
    });
}

/**
 * @since 0.0.1.0
 */
function getResursCallbacks() {
    getResursSpin('#div_' + resursPluginPrefix + '_admin_callbacks_button');
    getResursAjaxify('post', 'resursbank_get_new_callbacks', {'n': ''}, function (data) {
        if (data['reload'] === true || data['reload'] === '1') {
            $rQuery('#div_' + resursPluginPrefix + '_admin_callbacks_button').html(
                $rQuery('<div>', {
                    'style': 'font-weight: bold; color: #000099;'
                }).html(getResursLocalization('reloading'))
            );
            document.location.reload();
        } else if (data['error'] !== '') {
            getResursError(data['error'], '#div_' + resursPluginPrefix + '_admin_callbacks_button');
        } else {
            getResursError('Unable to update.', '#div_' + resursPluginPrefix + '_admin_callbacks_button');
        }
    }, function (data) {
        if (typeof data.statusText !== 'undefined') {
            getResursError(data.statusText, '#div_' + resursPluginPrefix + '_admin_callbacks_button');
        }
    });
}

/**
 * @since 0.0.1.0
 */
function getResursCallbackTest() {
    getResursSpin('#div_' + resursPluginPrefix + '_admin_trigger_callback_button');
    getResursAjaxify('post', 'resursbank_get_trigger_test', {}, function (response) {
        $rQuery('#div_' + resursPluginPrefix + '_admin_trigger_callback_button').html(
            $rQuery('<div>', {
                'style': 'font-weight: bold; color: #000099;'
            }).html(response['html'])
        );

        var testElement = $rQuery('#resursWaitingForTest');
        if (testElement.length > 0) {
            testElement.css('color', '#000099');
            resursCallbackReceiveSuccess = false;
            testElement.html(getResursLocalization('waiting_for_callback'));
            getResursCallbackResponse();
            resursCallbackTestHandle = setInterval(getResursCallbackResponse, resursCallbackActiveInterval * 1000);
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
        // Ask for the response one last time.
        $rQuery('#resursWaitingForTest').html('OK');
        getResursAjaxify('post', 'resursbank_get_trigger_response', {"runTime": resursCallbackActiveTime}, function (response) {
            $rQuery('#resursWaitingForTest').html(response['lastResponse']);
            if (response['success'] === 1 || response['success'] === true) {
                $rQuery('#resursWaitingForTest').css('color', '#009900');
            }
        });
    }
}

/**
 * Plugin defaults restoration. Dual checks for nonces, is_admin, ensuring this can't be sent into the system
 * by anonymous forces.
 * @since 0.0.1.4
 */
function rbwcResetThisPlugin() {
    if (confirm(getResursLocalization('cleanup_warning'))) {
        getResursAjaxify(
            'post',
            'resursbank_reset_plugin_settings',
            {
                'n': true
            },
            function (response) {
                if (typeof response.finished !== 'undefined') {
                    alert(getResursLocalization('cleanup_reload'));
                } else {
                    alert(getResursLocalization('cleanup_failed'));
                }
            },
            function () {
                alert(getResursLocalization('cleanup_failed'));
            }
        );
    } else {
        alert(getResursLocalization('cleanup_aborted'));
    }
}

/**
 * Plugin defaults restoration. Dual checks for nonces, is_admin, ensuring this can't be sent into the system
 * by anonymous forces.
 * @since 0.0.1.7
 */
function rbwcResetVersion22() {
    if (confirm(getResursLocalization('old_cleanup_warning'))) {
        getResursAjaxify(
            'post',
            'resursbank_reset_old_plugin_settings',
            {
                'n': true
            },
            function (response) {
                if (typeof response.finished !== 'undefined') {
                    document.location.reload();
                } else {
                    alert(getResursLocalization('old_cleanup_failed'));
                }
            },
            function () {
                alert(getResursLocalization('old_cleanup_failed'));
            }
        );
    } else {
        alert(getResursLocalization('old_cleanup_aborted'));
    }
}

/**
 * Update payment method description.
 * @param o
 * @since 0.0.1.5
 */
function rbwcUpdateMethodDescription(o) {
    $rQuery('#' + o.id).css('background-color', '#7bbbff');
    $rQuery('#' + o.id).attr('readonly', true);

    getResursAjaxify(
        'post',
        'resursbank_update_payment_method_description',
        {
            'n': true,
            'id': o.id,
            'value': o.value
        },
        function (response) {
            $rQuery('#' + o.id).attr('readonly', false);
            if (typeof response.allowed && response.allowed) {
                $rQuery('#' + o.id).css('background-color', '#99e79b');
            } else {
                alert(getResursLocalization('failed'));
                $rQuery('#' + o.id).css('background-color', '#f59e9e');
            }
        }
    );
}

/**
 * Update payment method description.
 * @param o
 * @since 0.0.1.5
 */
function rbwcUpdateMethodFee(o) {
    $rQuery('#' + o.id).css('background-color', '#7bbbff');
    $rQuery('#' + o.id).attr('readonly', true);

    getResursAjaxify(
        'post',
        'resursbank_update_payment_method_fee',
        {
            'n': true,
            'id': o.id,
            'value': o.value
        },
        function (response) {
            $rQuery('#' + o.id).attr('readonly', false);
            if (typeof response.allowed && response.allowed) {
                $rQuery('#' + o.id).css('background-color', '#99e79b');
                $rQuery('#' + o.id).val(response.newValue);
            } else {
                $rQuery('#' + o.id).css('background-color', '#f59e9e');
            }
        }
    );
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
    if ($rQuery('#' + resursPluginPrefix + '_admin_password').length > 0) {
        // One time nonce controlled credential importer.
        getDeprecatedCredentialsForm();
        getResursCredentialsTestForm($rQuery('#' + resursPluginPrefix + '_admin_password'), '' + resursPluginPrefix + '_admin_password');
        getResursCredentialsTestForm($rQuery('#' + resursPluginPrefix + '_admin_password_production'), '' + resursPluginPrefix + '_admin_password_production');
        getResursCredentialDivs();
    }
}

/**
 * @since 0.0.1.0
 */
function getResursCredentialDivs() {
    // Create an empty div here for credentials stuff.
    $rQuery('#' + resursPluginPrefix + '_admin_login').parent().children('.description').before(
        $rQuery(
            '<div>',
            {
                'id': 'resurs_credentials_test_username_box'
            }
        )
    );
    $rQuery('#' + resursPluginPrefix + '_admin_login_production').parent().children('.description').before(
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
                    if (typeof data['message'] === 'string' && data['message'] !== '') {
                        alert(data['message']);
                    }
                    if (typeof data === 'string' && data !== '') {
                        alert(data);
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
    if ($rQuery('#' + resursPluginPrefix + '_admin_password').length > 0) {
        getResursSpin('#resurs_import_credentials_result');
        getResursAjaxify('post', 'resursbank_import_credentials', {}, function (data) {
            if (data['login'] !== '' && data['pass'] !== '') {
                resursJustImported = true;
                $rQuery('#resurs_import_credentials_result').html(getResursLocalization('credential_import_success'));
                $rQuery('#' + resursPluginPrefix + '_admin_login').val(data['login']);
                $rQuery('#' + resursPluginPrefix + '_admin_password').val(data['pass']);
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
            apiLoginBox = '#' + resursPluginPrefix + '_admin_login_production';
            apiPwBox = '#' + resursPluginPrefix + '_admin_password_production';
            resultBox = '#' + resursPluginPrefix + '_admin_password_production_result';
            break;
        default:
            apiLoginBox = '#' + resursPluginPrefix + '_admin_login';
            apiPwBox = '#' + resursPluginPrefix + '_admin_password';
            resultBox = '#' + resursPluginPrefix + '_admin_password_result';
    }

    if ($rQuery('#' + resursPluginPrefix + '_admin_password').length > 0) {
        getResursSpin(resultBox);
        var uData = {
            'p': $rQuery(apiPwBox).val(),
            'u': $rQuery(apiLoginBox).val(),
            'e': $rQuery('#' + resursPluginPrefix + '_admin_environment').val()
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
    var checkoutType = $rQuery('#' + resursPluginPrefix + '_admin_checkout_type');
    if (checkoutType.length > 0) {
        $rQuery('#' + resursPluginPrefix + '_admin_checkout_type').parent().children('.description').html(
            getResursLocalization('translate_checkout_' + checkoutType.val())
        );
    }
}

/**
 * @param current
 * @since 0.0.1.0
 */
function resursUpdateFlowDescription(current) {
    $rQuery('#' + resursPluginPrefix + '_admin_checkout_type').parent().children('.description').html(
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
    $rQuery('#' + resursPluginPrefix + '_admin_waitForFraudControl').attr('checked', 'checked');
    if ($rQuery('.waitForFraudControlWarning').length === 0) {
        $rQuery('#' + resursPluginPrefix + '_admin_annulIfFrozen').parent().after(
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
    if ($rQuery('#' + resursPluginPrefix + '_admin_waitForFraudControl').length) {
        var fraudSettings = {
            'waitForFraudControl': $rQuery('#' + resursPluginPrefix + '_admin_waitForFraudControl').is(':checked'),
            'annulIfFrozen': $rQuery('#' + resursPluginPrefix + '_admin_annulIfFrozen').is(':checked'),
            'finalizeIfBooked': $rQuery('#' + resursPluginPrefix + '_admin_finalizeIfBooked').is(':checked'),
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
    var returnBooleanValue = false;
    if (currentAnnuityField.length > 0) {
        returnBooleanValue = currentAnnuityField.attr('class').indexOf('r_annuity_disabled') > -1 ? true : false;
    }
    return returnBooleanValue;
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

/**
 * Set state on payment method live.
 *
 * @param o
 * @since 0.0.1.6
 */
function rbwcMethodState(o) {
    $rQuery('#td_' + o.id).css('background-color', '#7bbbff');

    getResursAjaxify(
        'get',
        'resursbank_set_method_state',
        {
            'n': '',
            'id': o.id,
            'value': o.value,
            'checked': o.checked
        },
        function (data) {
            if (typeof data['newState'] !== 'undefined') {
                if (data['newState'] !== true) {
                    $rQuery('#td_' + o.id).css('background-color', '#f59e9e');
                    if (o.checked === true) {
                        $rQuery('#' + o.id).prop('checked', false);
                    } else {
                        $rQuery('#' + o.id).prop('checked', true);
                    }
                    alert(getResursLocalization('method_state_change_failure'));
                } else {
                    $rQuery('#td_' + o.id).css('background-color', '#99e79b');
                }
            } else {
                alert(getResursLocalization('method_state_change_failure'));
            }
        }
    );
}
