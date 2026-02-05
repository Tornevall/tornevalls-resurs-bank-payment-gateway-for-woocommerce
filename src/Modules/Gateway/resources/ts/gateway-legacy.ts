/**
 * RWS integration for WooCommerce legacy (shortcode-based) checkout.
 *
 * This script handles:
 * 1. Initializing RWS widget context with session token, amount, and customer type
 * 2. Updating RWS context when checkout updates (cart changes, customer type changes)
 * 3. Listening to resursPaymentMethodSelected events to sync radio button selection
 * 4. Replacing title/icon elements with RWS custom elements
 */

// Declare the data structure passed from PHP via wp_localize_script.
declare const ResursbankLegacyData: {
    rws_session_token: string;
    rws_type_map: Record<string, string>; // Payment method ID to RWS type
};

// Declare the global resurs object from the RWS payment widget library.
declare global {
    interface Window {
        resurs?: {
            updatePaymentMethods: (params: {
                amount?: string;
                token?: string;
                customerType?: 'NATURAL' | 'LEGAL';
            }) => void;
        };
    }
}

// Declare jQuery for legacy checkout.
declare const jQuery: any;

/**
 * Track the currently selected RWS payment method ID.
 * This is updated when user selects a payment method within an RWS widget.
 */
let selectedRwsMethodId: string | null = null;

/**
 * Get the currently selected RWS payment method ID.
 * Used by payment processing to determine which actual method was selected.
 */
export const getSelectedRwsMethodId = (): string | null => selectedRwsMethodId;

/**
 * Determine customer type based on company field.
 * In legacy checkout, the field ID is 'billing_company'.
 */
const getCustomerType = (): 'NATURAL' | 'LEGAL' => {
    const companyField = document.getElementById('billing_company') as HTMLInputElement;
    return companyField?.value === '' || !companyField ? 'NATURAL' : 'LEGAL';
};

/**
 * Get the cart total from the checkout page.
 * In legacy checkout, we extract this from the order review area.
 */
const getCartTotal = (): number => {
    // Try to get from the order total element.
    const totalElement = document.querySelector('.order-total .woocommerce-Price-amount bdi');
    if (totalElement) {
        // Extract numeric value from formatted price (e.g., "5,000.00 kr" -> 5000)
        const text = totalElement.textContent || '';
        const numericValue = text.replace(/[^0-9.,]/g, '').replace(',', '');
        const parsed = parseFloat(numericValue);
        if (!isNaN(parsed)) {
            return parsed;
        }
    }

    // Fallback: Try the data attribute if available.
    const reviewElement = document.querySelector('.woocommerce-checkout-review-order-table');
    if (reviewElement) {
        const dataTotal = reviewElement.getAttribute('data-total');
        if (dataTotal) {
            return parseFloat(dataTotal);
        }
    }

    return 0;
};

/**
 * Update RWS widget context with current values.
 * Called at startup and when cart/customer type changes.
 */
const updateRwsContext = (): void => {
    if (!window.resurs?.updatePaymentMethods) {
        return;
    }

    // Check if we have RWS data available.
    if (typeof ResursbankLegacyData === 'undefined' || !ResursbankLegacyData.rws_session_token) {
        return;
    }

    const amount = getCartTotal();
    const customerType = getCustomerType();

    window.resurs.updatePaymentMethods({
        token: ResursbankLegacyData.rws_session_token,
        amount: String(amount),
        customerType,
    });
};

/**
 * Replace payment method title and icon with RWS custom elements.
 * Called after checkout loads and after updates.
 */
const replacePaymentMethodLabels = (): void => {
    // Check if we have RWS data available.
    if (typeof ResursbankLegacyData === 'undefined' || !ResursbankLegacyData.rws_session_token) {
        return;
    }

    // Find all Resurs Bank payment method labels.
    const paymentMethods = document.querySelectorAll('.wc_payment_method');

    paymentMethods.forEach((methodElement) => {
        const radioInput = methodElement.querySelector('input[name="payment_method"]') as HTMLInputElement;
        if (!radioInput) {
            return;
        }

        const methodId = radioInput.value;
        const rwsType = ResursbankLegacyData.rws_type_map[methodId];

        // Only process if RWS type mapping exists.
        if (!rwsType) {
            return;
        }

        // Find the label element.
        const labelElement = methodElement.querySelector('label[for="payment_method_' + methodId + '"]');
        if (!labelElement) {
            return;
        }

        // Check if we've already replaced this label.
        if (labelElement.querySelector('resurs-payment-method-title')) {
            return;
        }

        // Store original content for potential fallback.
        const originalContent = labelElement.innerHTML;

        // Find or create the title container.
        // Legacy checkout structure: <label><img class="logo"> Title text</label>
        // We want: <label><resurs-payment-method-title> <resurs-payment-method-icon></label>

        // Clear the label content and add RWS elements.
        labelElement.innerHTML = '';

        const titleElement = document.createElement('resurs-payment-method-title');
        titleElement.setAttribute('type', rwsType);

        const iconElement = document.createElement('resurs-payment-method-icon');
        iconElement.setAttribute('type', rwsType);

        // Add a container div for consistent styling.
        const container = document.createElement('div');
        container.className = 'rb-payment-method-title rb-legacy-rws-label';
        container.appendChild(titleElement);
        container.appendChild(iconElement);

        labelElement.appendChild(container);

        // Store original content as data attribute for potential fallback.
        labelElement.setAttribute('data-original-content', originalContent);
    });
};

/**
 * Initialize the legacy checkout RWS integration.
 */
const init = (): void => {
    // Check if we have RWS data available.
    if (typeof ResursbankLegacyData === 'undefined') {
        return;
    }

    // Initial RWS context update.
    // Use a small delay to ensure the RWS library is loaded.
    setTimeout(() => {
        updateRwsContext();
        replacePaymentMethodLabels();
    }, 100);

    // Listen for RWS payment method selection events.
    window.addEventListener('resursPaymentMethodSelected', ((event: CustomEvent) => {
        if (event.detail === null) {
            // Payment method was deselected.
            selectedRwsMethodId = null;
            return;
        }

        const { methodId, type } = event.detail;

        if (methodId) {
            // Store the selected method ID for payment processing.
            selectedRwsMethodId = methodId;

            // Find and select the legacy checkout radio button.
            const radioInput = document.querySelector(
                `input[name="payment_method"][value="${methodId}"]`
            ) as HTMLInputElement;

            if (radioInput && !radioInput.checked) {
                radioInput.click();
            }
        }
    }) as EventListener);

    // Track previous values to avoid unnecessary updates.
    let previousAmount = getCartTotal();
    let previousCustomerType = getCustomerType();

    // Listen for WooCommerce checkout updates (cart changes, etc.).
    if (typeof jQuery !== 'undefined') {
        jQuery(document.body).on('updated_checkout', () => {
            const currentAmount = getCartTotal();
            const currentCustomerType = getCustomerType();

            // Only update if amount or customer type changed.
            if (currentAmount !== previousAmount || currentCustomerType !== previousCustomerType) {
                previousAmount = currentAmount;
                previousCustomerType = currentCustomerType;
                updateRwsContext();
            }

            // Re-apply label replacements after checkout update.
            replacePaymentMethodLabels();
        });

        // Also listen for payment method changes.
        jQuery(document.body).on('payment_method_selected', () => {
            // No specific action needed here, but could be useful for future enhancements.
        });

        // Listen for company field changes to update customer type.
        jQuery('#billing_company').on('change blur', () => {
            const currentCustomerType = getCustomerType();
            if (currentCustomerType !== previousCustomerType) {
                previousCustomerType = currentCustomerType;
                updateRwsContext();
            }
        });
    }
};

// Initialize when DOM is ready.
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
