import {dispatch, select} from '@wordpress/data';
// @ts-ignore
import {CART_STORE_KEY} from '@woocommerce/block-data';
// @ts-ignore
import {getSetting} from '@woocommerce/settings';

// Ignore missing Resursbank_GetAddress renders through Ecom Widget.
declare const Resursbank_GetAddress: any;

export class BlocksAddressUpdater {
    /**
     * Widget instance.
     */
    private widget: any = null;

    /**
     * Store all payment methods persistently.
     * @private
     */
    private allPaymentMethods: any[] = [];

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
                    company: 'fullName',
                };

                for (const [key, value] of Object.entries(map)) {
                    if (!data.hasOwnProperty(value)) {
                        throw new Error(
                            `Missing required field "${value}" in data object.`
                        );
                    }

                    if (key === 'company') {
                        this.setBillingAndShipping(cartData,
                            typeof data[value] === 'string' && this.widget.getCustomerType() === 'LEGAL' ? data[value] : '');
                        continue;
                    }

                    // Update both shipping and billing.
                    const addressValue = typeof data[value] === 'string' ? data[value] : '';
                    cartData.shippingAddress[key] = addressValue;
                    cartData.billingAddress[key] = addressValue;
                }

                // Dispatch the updated cart data back to the store
                dispatch(CART_STORE_KEY).setCartData(cartData);

                // Trigger update for payment methods by re-triggering cart actions
                this.refreshPaymentMethods();
            },
        });
    }

    setBillingAndShipping(cartData: any, value: any) {
        // Update both shipping and billing.
        cartData.shippingAddress.company = value;
        cartData.billingAddress.company = value;
    }

    /**
     * Configure the event listeners for the widget.
     */
    initialize() {
        const cartDataReady = select(CART_STORE_KEY).hasFinishedResolution('getCartData');
        if (!cartDataReady) {
            dispatch(CART_STORE_KEY).invalidateResolution('getCartData');
        }

        this.widget.setupEventListeners();
        this.loadAllPaymentMethods();
        this.refreshPaymentMethods();
    }

    /**
     * Load all payment methods from the store before iterating through it when getAddress are switching
     * customer types.
     */
    loadAllPaymentMethods() {
        const cartData = select(CART_STORE_KEY).getCartData();
        const paymentMethodsFromSettings = getSetting('resursbank_data', {}).payment_methods || [];

        const existingMethodIds = new Set(
            (cartData.paymentMethods || []).map((method: string) => method.toLowerCase())
        );

        this.allPaymentMethods = [...(cartData.paymentMethods || [])];

        paymentMethodsFromSettings.forEach((method: any) => {
            const methodKey = (method.id?.toLowerCase() || method.name?.toLowerCase()).trim();

            if (!existingMethodIds.has(methodKey)) {
                this.allPaymentMethods.push(methodKey);
            }
        });
    }

    /**
     * Resolve cart data from store and confirm the presence of shipping address
     * data since this is what we will be manipulating.
     */
    getCartData() {
        const data = select(CART_STORE_KEY).getCartData();
        const requiredFields = [
            'first_name', 'last_name', 'address_1', 'address_2',
            'postcode', 'city', 'country', 'company'
        ];

        if (!data.shippingAddress || requiredFields.some(field => data.shippingAddress[field] === undefined)) {
            throw new Error('Missing required shipping address data in cart.');
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

    /**
     * Trigger WooCommerce to recalculate cart and payment methods.
     */
    refreshPaymentMethods() {
        if (!this.allPaymentMethods.length) {
            console.log('No payment methods available for filtering.');
            this.loadAllPaymentMethods();
            return;
        }

        const cartData = select(CART_STORE_KEY).getCartData();
        const paymentMethods = cartData.paymentMethods;

        if (!paymentMethods) {
            console.warn('No payment methods found in cart data.');
            dispatch(CART_STORE_KEY).invalidateResolution('getCartData');
            return;
        }

        const paymentMethodsFromSettings = getSetting('resursbank_data', {}).payment_methods || [];

        const settingsMethodsMap = new Map(
            paymentMethodsFromSettings.map((method: any) => [
                method.id?.toLowerCase() || method.name?.toLowerCase(),
                method,
            ])
        );

        const isCorporate = this.widget.getCustomerType() === 'LEGAL' ||
            cartData.billingAddress?.company?.trim() !== '';

        const cartTotal =
            parseInt(cartData.totals.total_price, 10) /
            Math.pow(10, cartData.totals.currency_minor_unit);

        // Iterate over all cart methods and update their availability.
        const updatedPaymentMethods = this.allPaymentMethods.map((cartMethod: any) => {
            const normalizedCartMethodId = cartMethod?.toLowerCase().trim(); // Normalize the `cartMethod`.
            const methodFromSettings = settingsMethodsMap.get(normalizedCartMethodId);
            if (methodFromSettings) {
                const { // @ts-ignore
                    enabled_for_legal_customer, // @ts-ignore
                    enabled_for_natural_customer, // @ts-ignore
                    min_purchase_limit, // @ts-ignore
                    max_purchase_limit
                } =
                    methodFromSettings;

                // Include methods based on customer type or both flags being true.
                const supportsCustomerType =
                    (isCorporate && enabled_for_legal_customer) ||
                    (!isCorporate && enabled_for_natural_customer) ||
                    (!isCorporate && enabled_for_legal_customer && enabled_for_natural_customer);

                // Validate purchase limits.
                const withinPurchaseLimits =
                    cartTotal >= min_purchase_limit && cartTotal <= max_purchase_limit;

                if (supportsCustomerType && withinPurchaseLimits) {
                    return cartMethod; // Keep the method if it meets all conditions.
                }

                return null; // Exclude the method if it doesn't meet the conditions.
            }

            // If it's not a custom method, retain it as-is.
            return cartMethod;
        }).filter(Boolean);

        dispatch(CART_STORE_KEY).setCartData({
            ...cartData,
            paymentMethods: updatedPaymentMethods,
        });
    }
}
