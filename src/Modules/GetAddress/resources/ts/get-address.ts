/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

/**
 * This is a generic implementation of the Get Address widget
 * which works for both the legacy and blocks based WooCommerce
 * checkouts.
 */
declare var Resursbank_GetAddress: any;

document.addEventListener(
    'DOMContentLoaded',
    function () {
        /**
         * Fields are prefixed "billing-" in blocks based checkout
         * and "billing_" in legacy checkout. Since we use the
         * same code for both, we need to check for both. Looking
         * for some parent element to determine which checkout
         * we've rendered is unsafe because WC frequently changes
         * the naming scheme of their elements. It's safer to just
         * scan for both versions of the ID.
         */
        const getElementById = (field: string) => {
            return document.getElementById(`billing-${field}`) || document.getElementById(`billing_${field}`);
        }

        /**
         * For other input types, use the native setter to ensure
         * any frameworks (like React) are notified of the change.
         * This ensures that, for both blocks and legacy checkout,
         * the state is updated correctly and payment methods are
         * reloaded.
         */
        const setInputValue = (field: string, value: string) => {
            const input = getElementById(field);

            if (input === null) {
                return;
            }

            // Use native input value setter to ensure frameworks
            // (like React) are notified of the change.
            const nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, "value")?.set;
            nativeInputValueSetter?.call(input, value);

            // Manually trigger input event to ensure that data
            // is placed in state for the blocks based checkout.
            input.dispatchEvent(new Event('input', {bubbles: true}));
        }

        // Setup special listener for changes on the company name input.
        //
        // When the company name changes, trigger the onchange event on
        // the country field to ensure that we reload payment methods.
        getElementById('company')?.addEventListener('change', function() {
            // Remember current postcode value.
            const postcode = (getElementById('postcode') as HTMLInputElement)?.value || '';

            getElementById('country')?.dispatchEvent(new Event('change', {bubbles: true}));

            // Re-apply postcode to trigger any postcode related events.
            if (postcode !== '') {
                setInputValue('postcode', postcode);
            }
        });

        // Configure instance of get address widget component.
        const getAddressInstance = new Resursbank_GetAddress({
            updateAddress: (data: any) => {
                // Function to set billing field value and dispatch events to
                // execute events that will update state (blocks) and reload
                // payment methods (both blocks and legacy).
                const setBillingField = function(field: string, value: string) {
                    const input = getElementById(field);

                    if (input instanceof HTMLSelectElement) {
                        // For select elements, set the value directly and
                        // dispatch a change event to the value in the field
                        // is updated correctly and payment methods reload
                        // in both legacy and blocks checkout.
                        input.value = value;
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    } else if (input !== null) {
                        setInputValue(field, value);
                    }
                }

                setBillingField('first_name', data.firstName);
                setBillingField('last_name', data.lastName);
                setBillingField('address_1', data.addressRow1);
                setBillingField('address_2', data.addressRow2);
                setBillingField('country', data.countryCode);
                setBillingField('city', data.postalArea);
                setBillingField('postcode', data.postalCode);
                setBillingField('company', getAddressInstance.getCustomerType() === 'LEGAL' ? data.fullName : '');
            }
        });

        getAddressInstance.setupEventListeners();
    }
);
