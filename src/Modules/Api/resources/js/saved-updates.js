/**
 * Automatically load the saved new store country values from the API after WordPress saved its value.
 * This should only occur when the configuration has been updated with a new store.
 */

// Prepare country code data.
const ajaxPromise = fetch(rbApiAdminLocalize.url)
    .then(response => response.json())
    .then(data => data)
    .catch(error => {
        console.error('Error:', error);
        return null;
    });

// Get ready to check the store country selector.
jQuery(document).ready(function($) {
    const storeCountrySelector = $('#resursbank_store_country');

    if (storeCountrySelector.length) {
        // Create and append the spinner next to the input field
        const spinner = $('<span class="spinner country-code-spinner"></span>');
        storeCountrySelector.after(spinner);
        spinner.hide();

        // Wait for the AJAX response and update the input field
        ajaxPromise.then(data => {
            if (data) {
                const storeCountry = data?.storeCountry || 'N/A';
                storeCountrySelector.attr('value', storeCountry);
            }
        });

        // Show the spinner during the AJAX request
        spinner.css('visibility', 'visible').show();

        // Hide the spinner once the AJAX request completes
        ajaxPromise.finally(() => {
            spinner.css('visibility', 'hidden').hide();
        });
    }
});
