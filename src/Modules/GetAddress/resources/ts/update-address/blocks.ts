import { select, dispatch } from '@wordpress/data';
// @ts-ignore
import { CART_STORE_KEY } from '@woocommerce/block-data';

// Ignore missing Resursbank_GetAddress renders through Ecom Widget.
declare const Resursbank_GetAddress: any;

export class BlocksAddressUpdater {
    /**
     * Widget instance.
     */
    private widget: any = null;

    /**
     * Generate widget instance.
     */
    constructor() {
        // Initialize any properties if needed
        this.widget = new Resursbank_GetAddress({
            updateAddress: (data: any) => {
                // Reset store data (and consequently the form).
                this.resetCartData();

                // Get current cart data.
                let cartData = this.getCartData();

                const map = {
                    first_name: 'firstName',
                    last_name: 'lastName',
                    address_1: 'addressRow1',
                    address_2: 'addressRow2',
                    postcode: 'postalCode',
                    city: 'postalArea',
                    country: 'countryCode',
                    company: 'fullName'
                };

                for (const [key, value] of Object.entries(map)) {
                    if (!data.hasOwnProperty(value)) {
                        throw new Error(`Missing required field "${value}" in data object.`);
                    }

                    if (key === 'company') {
                        if (typeof data[value] === 'string' &&
                            this.widget.getCustomerType() === 'LEGAL'
                        ) {
                            cartData.shippingAddress.company = data[value];
                            continue;
                        }
                        continue;
                    }

                    cartData.shippingAddress[key] = typeof data[value] === 'string' ? data[value] : '';
                }

                // Dispatch the updated cart data back to the store
                dispatch(CART_STORE_KEY).setCartData(cartData);
            }
        });
    }

    /**
     * Configure the event listeners for the widget.
     */
    initialize() {
        this.widget.setupEventListeners();
    }

    /**
     * Resolve cart data from store and confirm the presence of shipping address
     * data since this is what we will be manipulating.
     */
    getCartData() {
        const data = select(CART_STORE_KEY).getCartData();

        // Validate presence of shippingAddress and all required fields.
        if (!data.shippingAddress) {
            throw new Error('Missing shipping address data in cart.');
        }

        // Loop through all required fields and ensure they are present.
        const requiredFields = [
            'first_name',
            'last_name',
            'address_1',
            'address_2',
            'postcode',
            'city',
            'country',
            'company'
        ];

        for (const field of requiredFields) {
            if (data.shippingAddress[field] === undefined) {
                throw new Error(`Missing required field "${field}" in shipping address data.`);
            }
        }

        return data;
    }

    /**
     * Reset cart data.
     */
    resetCartData() {
        let cartData = this.getCartData();

        // Clear address.
        cartData.shippingAddress.first_name = '';
        cartData.shippingAddress.last_name = '';
        cartData.shippingAddress.address_1 = '';
        cartData.shippingAddress.address_2 = '';
        cartData.shippingAddress.postcode = '';
        cartData.shippingAddress.city = '';
        cartData.shippingAddress.country = '';
        cartData.shippingAddress.company = '';

        // Dispatch the updated cart data back to the store
        dispatch(CART_STORE_KEY).setCartData(cartData);
    }
}