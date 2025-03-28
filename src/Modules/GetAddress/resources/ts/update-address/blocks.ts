import {dispatch, select} from '@wordpress/data';
// @ts-ignore
import {CART_STORE_KEY} from '@woocommerce/block-data';
// @ts-ignore
import {getSetting} from '@woocommerce/settings';
import {BlocksCustomerType} from "./customer";

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
     * Customer Type Update Action.
     * @private
     */
    private customerTypeUpdater: any;

    /**
     * Use billing element.
     * @private
     */
    private useBillingElement: any;

    /**
     * Generate widget instance.
     */
    constructor(useWidget: boolean) {
        this.customerTypeUpdater = new BlocksCustomerType();

        this.initializeUseBillingElement();

        // Initialize any properties if needed
        if (typeof Resursbank_GetAddress !== 'undefined') {
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
                            this.setBillingAndShipping(
                                cartData,
                                typeof data[value] === 'string' && this.widget.getCustomerType() === 'LEGAL' ? data[value] : ''
                            );
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
        } else {
            this.loadAllPaymentMethods();
            this.refreshPaymentMethods();
        }

        this.addCartUpdateListener('#shipping-company');
        this.addCartUpdateListener('#billing-company');
    }

    /**
     * Add a listener to the specified field to trigger payment method updates on changes.
     *
     * @param fieldName
     */
    addCartUpdateListener(fieldName: string) {
        const mutationObserver = new MutationObserver(() => {
            const companyField = document.querySelector(fieldName);

            if (companyField) {
                mutationObserver.disconnect();

                // @ts-ignore
                resursConsoleLog(`Listener add: ${fieldName}`, 'DEBUG');

                companyField.addEventListener('change', (event) => {
                    // @ts-ignore
                    resursConsoleLog(`${fieldName} has changed`, 'DEBUG');
                    this.refreshPaymentMethods();
                });
            }
        });

        mutationObserver.observe(document.body, {
            childList: true,
            subtree: true,
        });
    }

    /**
     * Initialize the useBillingElement and set up an observer if it doesn't exist.
     */
    private initializeUseBillingElement() {
        // Try to find the element initially
        const element = document.querySelector<HTMLInputElement>('.wc-block-checkout__use-address-for-billing input[type="checkbox"]');
        if (element) {
            // @ts-ignore
            resursConsoleLog("useBillingElement found during initialization.", 'DEBUG');
            this.useBillingElement = element;
            return;
        }

        // @ts-ignore
        // Set up a MutationObserver to detect when the element is added
        resursConsoleLog("useBillingElement not found. Setting up observer...", 'DEBUG');
        const observer = new MutationObserver((mutations, obs) => {
            const observedElement = document.querySelector<HTMLInputElement>('.wc-block-checkout__use-address-for-billing input[type="checkbox"]');
            if (observedElement) {
                // @ts-ignore
                resursConsoleLog("useBillingElement found by observer.", 'DEBUG');
                this.useBillingElement = observedElement;
                obs.disconnect(); // Stop observing once the element is found
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
        });
    }

    /**
     * Update billing and shipping address in cartData.
     * @param cartData
     * @param value
     */
    setBillingAndShipping(cartData: any, value: any) {
        // Update both shipping and billing.
        cartData.shippingAddress.company = value;
        cartData.billingAddress.company = value;
    }

    /**
     * Configure the event listeners for the getAddress-widget.
     *
     * @param widgetEnabled
     */
    initialize(widgetEnabled: boolean) {
        const cartDataReady = select(CART_STORE_KEY).hasFinishedResolution('getCartData');
        if (!cartDataReady) {
            // @ts-ignore
            resursConsoleLog('Cart data not ready, triggered dispatch.', 'DEBUG');
            dispatch(CART_STORE_KEY).invalidateResolution('getCartData');
        }

        if (widgetEnabled) {
            this.widget.setupEventListeners();
        }
        this.loadAllPaymentMethods();
        this.refreshPaymentMethods();
    }

    /**
     * Load all payment methods from the store before iterating through it when getAddress are switching
     * customer types.
     */
    loadAllPaymentMethods() {
        // @ts-ignore
        resursConsoleLog('Loading internal payment methods.', 'DEBUG');
        // Initially build a full list, locally, of available payment methods.
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
     * Determine whether billing is being used based on the checkbox state.
     */
    usingBilling(): boolean {
        if (!this.useBillingElement) {
            console.warn("useBillingElement is not initialized. Defaulting to billing.");
            return true; // Default to billing if the element is not initialized
        }

        // @ts-ignore
        resursConsoleLog("Use same address for billing:", this.useBillingElement.checked);
        return !this.useBillingElement.checked; // Return true when unchecked (use billing)
    }

    /**
     * Trigger WooCommerce to recalculate cart and payment methods.
     */
    refreshPaymentMethods() {
        if (!this.allPaymentMethods.length) {
            // @ts-ignore
            resursConsoleLog('No payment methods available for filtering.', 'DEBUG');
            this.loadAllPaymentMethods();
            return;
        }

        // @ts-ignore
        resursConsoleLog('Refreshing internal payment methods.', 'DEBUG');

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

        // In blocks, shipping address has higher priority than billing when it comes
        // to company names.
        const isCorporate = this.widget?.getCustomerType() === 'LEGAL' ||
            (
                this.usingBilling()
                    ? cartData.billingAddress?.company?.trim() !== ''
                    : cartData.shippingAddress?.company?.trim() !== ''
            );

        const cartTotal =
            parseInt(cartData.totals.total_price, 10) /
            Math.pow(10, cartData.totals.currency_minor_unit);

        this.customerTypeUpdater.updateCustomerType(isCorporate ? 'LEGAL' : 'NATURAL');

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
                    // @ts-ignore
                    resursConsoleLog( // @ts-ignore
                        methodFromSettings.title + ', ' + cartTotal + ': Approved limit and supported customer type.',
                        'DEBUG'
                    );
                    return cartMethod; // Keep the method if it meets all conditions.
                }

                // @ts-ignore
                resursConsoleLog( // @ts-ignore
                    methodFromSettings.title + ', Cart total ' + cartTotal + ': ' + (withinPurchaseLimits ? 'OK: Within' : 'Not OK: Outside') + ' limit. ' +
                    (supportsCustomerType ? 'Customer type supported (OK).' : 'Customer type not supported (Not OK).'),
                    'DEBUG'
                );

                return null; // Exclude the method if it doesn't meet the conditions.
            }

            // If it's not a custom method, retain it as-is.
            return cartMethod;
        }).filter(Boolean);

        //dispatch(CART_STORE_KEY).invalidateResolution('getCartData');
        dispatch(CART_STORE_KEY).setCartData({
            ...cartData,
            paymentMethods: updatedPaymentMethods,
        });
    }
}
