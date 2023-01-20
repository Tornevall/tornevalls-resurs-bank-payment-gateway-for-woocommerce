jQuery(document).ready(function ($) {
    if (rbHasData()) {
        rbBindGetAddressButtons();

        // Change radio button status depending on the customer type stored in session.
        if (typeof rbCustomerTypeData === 'object' && typeof rbCustomerTypeData['currentCustomerType'] === 'string') {
            if (rbCustomerTypeData['currentCustomerType'] === 'LEGAL') {
                $('#rb-customer-widget-getAddress-customerType-legal').attr('checked', 'checked')
            } else {
                $('#rb-customer-widget-getAddress-customerType-natural').attr('checked', 'checked')
            }
        }
    }
});

/**
 * Quick check for fields required to use features in this script.
 * @returns {boolean}
 */
function rbHasData() {
    let hasGetAddress = jQuery('#rb-customer-widget-getAddress-customerType-natural').length > 0;
    return typeof rbCustomerTypeData !== 'undefined' && hasGetAddress;
}

/**
 * Bind getAddress button to WooCommerce checkout updates.
 */
function rbBindGetAddressButtons() {
    // Billing company should override the radios.
    jQuery('#billing_company').on(
        'change',
        function () {
            if (rbIsCompany()) {
                jQuery('#rb-customer-widget-getAddress-customerType-legal').attr('checked', 'checked');
                rbSetCustomerType('LEGAL');
            } else {
                rbSetCustomerType('NATURAL');
            }
        });
    jQuery('#rb-customer-widget-getAddress-customerType-legal').on(
        'change',
        function () {
            rbSetCustomerType('LEGAL');
        }
    );
    jQuery('#rb-customer-widget-getAddress-customerType-natural').on(
        'change',
        function () {
            // Allow legal when company is empty.
            if (jQuery('#billing_company').val() === '') {
                rbSetCustomerType('NATURAL');
            }
        }
    );
}

/**
 * Check if company is filled in.
 * @returns {boolean}
 */
function rbIsCompany() {
    let billingCompany = jQuery('#billing_company');
    return billingCompany.val() !== ''
}

/**
 * Update customer type in session backend.
 * @param setCustomerTypeValue
 */
function rbSetCustomerType(setCustomerTypeValue) {
    if (rbHasData() && !rbCanSetCustomerType(setCustomerTypeValue)) {
        jQuery.ajax(
            {
                url: rbCustomerTypeData['apiUrl'] + '&customerType=' + setCustomerTypeValue,
            }
        ).done(
            function (result) {
                if (typeof result === 'object' && result['update']) {
                    jQuery('body').trigger('update_checkout', {'korv': 'present'});
                } else {
                    alert("Unable to update customer type.");
                }
            }
        )
    }
}

/**
 * Look up and validate if customer type is selected or not.
 * @param customerTypeLookup
 */
function rbCanSetCustomerType(customerTypeLookup) {
    let customerTypeField = jQuery('#rb-customer-widget-getAddress-customerType-' + customerTypeLookup.toLowerCase());

    console.log(!!(customerTypeLookup + " is settable: " + (customerTypeField.length > 0 && !customerTypeField.prop('checked'))));
    return customerTypeField.length > 0 && !customerTypeField.prop('checked');
}
