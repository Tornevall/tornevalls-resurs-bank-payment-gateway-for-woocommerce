/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

/**
 * Customer Type Handler.
 *
 * This handler is still in use. A rewrite could be considered since the customer type is stored and handled within
 * the widget as of ecom v3. However, as it's likely preferable to trust WooCommerce data fields over the
 * getAddress widget, we will continue to prioritize the WooCommerce fields.
 */

/**
 * Preparing for stuff in checkout, possibly needed by Resurs getAddress widget.
 */
jQuery(document).ready(function ($) {
    if (rbHasCompanyField()) {
        // Handle our custom fields instantly, making sure things are displayed correctly for
        // defaults and customizations at init.
        handleResursCustomerTypeCompany();
        rbUpdateCustomerType();
        // Billing company should override the radios.
        jQuery('#billing_company')
            .on(
                'blur',
                function () {
                    rbUpdateCustomerType();
                }
            )
            .on(
                'keyup',
                function () {
                    // Faster way to handle company government id's is to also
                    // check keypresses, so we don't need to wait for the blur to occur.
                    handleResursCustomerTypeCompany();
                }
            )
    }
})

/**
 * Get the current chosen customer type.
 * @returns {string}
 */
function getResursCustomerType() {
    return (rbIsCompany() ? 'LEGAL' : 'NATURAL');
}

/**
 * Update customer type in session backend.
 */
function rbUpdateCustomerType() {
    if (rbHasCompanyField()) {
        jQuery.ajax(
            {
                url: rbCustomerTypeData['apiUrl'] + '&customerType=' + getResursCustomerType(),
            }
        ).done(
            function (result) {
                if (typeof result === 'object' && result['update']) {
                    handleResursCustomerTypeCompany();
                    jQuery('body').trigger('update_checkout');
                } else {
                    alert("Unable to update customer type.");
                }
            }
        )
    }
}

/**
 * Handle company specific features.
 */
function handleResursCustomerTypeCompany() {
    if (rbHasCompanyField()) {
        // The optional string displayed by woocommerce are forced by woocommerce.
        // For Resurs, this field works differently since it is normally hidden as long as
        // the company name is not filled in, and when filled in combined with LEGAL
        // payments it is no longer optional (see Gateway.php for more details).
        jQuery('#billing_resurs_government_id_field label > .optional').remove();
        if (getResursCustomerType() === 'LEGAL') {
            // Making this field more noticeable to customers by animating it slightly.
            jQuery('#billing_resurs_government_id_field').show('fast');
        } else {
            jQuery('#billing_resurs_government_id_field').hide();
        }
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
