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
                    // Map API response fields to WooCommerce address fields
                    const map: Record<string, string> = {
                        first_name: 'firstName',
                        last_name: 'lastName',
                        address_1: 'addressRow1',
                        address_2: 'addressRow2',
                        postcode: 'postalCode',
                        city: 'postalArea',
                        country: 'countryCode',
                        company: 'fullName',
                    };

                    // Build address object from API response
                    const address: Record<string, string> = {};

                    for (const [wcField, apiField] of Object.entries(map)) {
                        if (!data.hasOwnProperty(apiField)) {
                            throw new Error(
                                `Missing required field "${apiField}" in data object.`
                            );
                        }

                        if (wcField === 'company') {
                            // Only set company for LEGAL customer type
                            address[wcField] = typeof data[apiField] === 'string' &&
                                this.widget.getCustomerType() === 'LEGAL'
                                    ? data[apiField]
                                    : '';
                        } else {
                            address[wcField] = typeof data[apiField] === 'string'
                                ? data[apiField]
                                : '';
                        }
                    }

                    // Use WooCommerce Blocks' dedicated address update actions
                    // These properly trigger store updates and re-renders
                    dispatch(CART_STORE_KEY).setShippingAddress(address);
                    dispatch(CART_STORE_KEY).setBillingAddress(address);

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
                companyField.addEventListener('change', () => {
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
            this.useBillingElement = element;
            return;
        }

        // Set up a MutationObserver to detect when the element is added
        const observer = new MutationObserver((mutations, obs) => {
            const observedElement = document.querySelector<HTMLInputElement>('.wc-block-checkout__use-address-for-billing input[type="checkbox"]');
            if (observedElement) {
                this.useBillingElement = observedElement;
                obs.disconnect();
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
        });
    }

    /**
     * Configure the event listeners for the getAddress-widget.
     *
     * @param widgetEnabled
     */
    initialize(widgetEnabled: boolean) {
        const cartDataReady = select(CART_STORE_KEY).hasFinishedResolution('getCartData');
        if (!cartDataReady) {
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
     * Determine whether billing is being used based on the checkbox state.
     */
    usingBilling(): boolean {
        if (!this.useBillingElement) {
            return true;
        }

        return !this.useBillingElement.checked;
    }

    /**
     * Trigger WooCommerce to recalculate cart and payment methods.
     */
    refreshPaymentMethods() {
        if (!this.allPaymentMethods.length) {
            this.loadAllPaymentMethods();
            return;
        }

        const cartData = select(CART_STORE_KEY).getCartData();
        const paymentMethods = cartData.paymentMethods;

        if (!paymentMethods) {
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
                    return cartMethod;
                }

                return null;
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
