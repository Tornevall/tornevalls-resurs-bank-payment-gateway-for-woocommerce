// @ts-ignore
import * as jQuery from 'jquery';

/**
 * BlocksCustomerType class handles interactions with the customer type in the checkout process.
 * resursbankabpaygwFrontendData is expected through internal localization.
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
    private updateCustomerType(customerType: string) {
        // @ts-ignore
        const apiUrl = resursbankabpaygwFrontendData?.apiUrl;

        if (!apiUrl) {
            console.error('API URL is undefined');
            return;
        }

        jQuery.ajax({
            type: 'GET',
            url: apiUrl,
            data: {
                customerType: customerType,
            },
            dataType: 'json',
            success: (response: any) => {
                if (response && response.customerType) {
                    // @ts-ignore
                    resursbankabpaygwConsoleLog("Updated customer: " + response.customerType, 'DEBUG');
                    // Trigger the update_checkout event on successful AJAX call.
                    jQuery(document.body).trigger('update_checkout');
                } else {
                    console.warn('Invalid response structure:', response);
                }
            },
            error: (xhr: any, status: string, error: string) => {
                console.error('Error updating customer type:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText,
                    responseStatus: xhr.status,
                });
            },
        });
    }
}


