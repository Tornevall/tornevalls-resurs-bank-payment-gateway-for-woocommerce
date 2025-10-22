// @ts-ignore
import * as jQuery from 'jquery';

// @todo Not sure this works well if it's even really used, the selection indicators on frontend doesn't reflect selection atm. graphically.

/**
 * BlocksCustomerType class handles interactions with the customer type in the checkout process.
 * rbFrontendData is expected through internal localization.
 */
export class BlocksCustomerType {
    /**
     * Update the customer type in the checkout process.
     *
     * This is required by the checkout if payment methods should reload properly.
     * Sends an AJAX request to update the customer type and triggers the checkout update event.
     *
     * @param customerType The type of customer (LEGAL or NATURAL).
     */
    private updateCustomerType(customerType: string) { // @ts-ignore
        // @ts-ignore
        const apiUrl = rbFrontendData?.apiUrl; // Ensure the API URL is defined.
        if (!apiUrl) {
            console.error('API URL is undefined');
            return;
        }

        jQuery.ajax({
            url: `${apiUrl}&customerType=${customerType}`,
        }) // @ts-ignore
            .done((response) => {
                // @ts-ignore
                resursConsoleLog("Updated customer: " + response?.customerType, 'DEBUG');
                // Trigger the update_checkout event on successful AJAX call.
                jQuery(document.body).trigger('update_checkout');
            }) // @ts-ignore
            .fail((error) => {
                // Log any errors encountered during the AJAX call.
                console.error('Error updating customer type:', error);
            });
    }
}
