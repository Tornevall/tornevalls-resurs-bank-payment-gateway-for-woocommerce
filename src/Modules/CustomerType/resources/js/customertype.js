/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

jQuery(document).ready(function ($) {
    if (rbHasCompanyField()) {
        rbUpdateCustomerType();
        // Billing company should override the radios.
        jQuery('#billing_company').on(
            'blur',
            function () {
                rbUpdateCustomerType();
            }
        );
    }
});

/**
 * Update customer type in session backend.
 *
 * @param setCustomerTypeValue
 */
function rbUpdateCustomerType(setCustomerTypeValue) {
    if (rbHasCompanyField()) {
        jQuery.ajax(
            {
                url: rbCustomerTypeData['apiUrl'] + '&customerType=' + (rbIsCompany() ? 'LEGAL' : 'NATURAL'),
            }
        ).done(
            function (result) {
                if (typeof result === 'object' && result['update']) {
                    jQuery('body').trigger('update_checkout');
                } else {
                    alert("Unable to update customer type.");
                }
            }
        )
    }
}

/**
 * Check if company is filled in.
 *
 * @returns {boolean}
 */
function rbIsCompany() {
    return rbHasCompanyField() && jQuery('#billing_company').val() !== ''
}

/**
 * Look for the billing company.
 *
 * Since this script can be executed from other pages than the checkout, we'll do this check
 * to make sure we won't execute it when not necessary.
 *
 * @returns {boolean}
 */
function rbHasCompanyField() {
    return jQuery('#billing_company').length > 0;
}
