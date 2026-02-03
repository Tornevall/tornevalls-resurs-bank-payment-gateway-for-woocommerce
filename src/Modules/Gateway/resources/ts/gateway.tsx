import React from 'react';
import {select} from '@wordpress/data';

// @ts-ignore
import {CART_STORE_KEY} from '@woocommerce/block-data';
// @ts-ignore
import {registerPaymentMethod} from '@woocommerce/blocks-registry';
// @ts-ignore
import {getSetting} from '@woocommerce/settings';

const settings = getSetting('resursbank_data', {});

declare var Resursbank_PaymentMethod: any;

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

    // Declare RWS custom elements for JSX.
    namespace JSX {
        interface IntrinsicElements {
            'resurs-payment-method-title': React.DetailedHTMLProps<
                React.HTMLAttributes<HTMLElement> & { type?: string },
                HTMLElement
            >;
            'resurs-payment-method-icon': React.DetailedHTMLProps<
                React.HTMLAttributes<HTMLElement> & { type?: string },
                HTMLElement
            >;
            'resurs-payment-method-subtitle': React.DetailedHTMLProps<
                React.HTMLAttributes<HTMLElement> & { type?: string; amount?: string },
                HTMLElement
            >;
            'resurs-payment-method': React.DetailedHTMLProps<
                React.HTMLAttributes<HTMLElement> & { type?: string; token?: string; amount?: string },
                HTMLElement
            >;
        }
    }
}

/**
 * Determine customer type based on company field.
 * Same logic as canMakePayment uses.
 */
const getCustomerType = (): 'NATURAL' | 'LEGAL' => {
    const companyField = document.getElementById('billing-company') as HTMLInputElement;
    return companyField?.value === '' ? 'NATURAL' : 'LEGAL';
};

/**
 * Update RWS widget context with current values.
 * Called at startup and when cart/customer type changes.
 */
const updateRwsContext = (amount: number): void => {
    if (!window.resurs?.updatePaymentMethods || !settings.rws_session_token) {
        return;
    }

    window.resurs.updatePaymentMethods({
        token: settings.rws_session_token,
        amount: String(amount),
        customerType: getCustomerType(),
    });
};

(() => {
    if (typeof getSetting !== 'function') {
        console.error('WooCommerce: getSetting is not available.');
        return;
    }

    if (typeof registerPaymentMethod !== 'function') {
        console.error('WooCommerce Blocks: registerPaymentMethod is not available.');
        return;
    }

    if (typeof select !== 'function') {
        console.error('WooCommerce: select is not available.');
        return;
    }

    // Initialize RWS widget context with initial values.
    // Use a small delay to ensure the store is ready.
    setTimeout(() => {
        try {
            const cartData = select(CART_STORE_KEY).getCartData();
            if (cartData?.totals?.total_price) {
                const cartTotal = parseInt(cartData.totals.total_price, 10) /
                    Math.pow(10, cartData.totals.currency_minor_unit);
                updateRwsContext(cartTotal);
            }
        } catch (e) {
            // Store may not be ready yet, will be updated by Content component.
        }
    }, 100);

    // Track previous values to avoid unnecessary updates.
    let previousAmount = 0;
    let previousCustomerType = getCustomerType();

    // Set up store subscription to sync RWS context on cart/billing changes.
    // This runs once globally, not per payment method.
    const store = select(CART_STORE_KEY);
    if (store?.subscribe) {
        store.subscribe(() => {
            try {
                const cartData = store.getCartData();
                if (!cartData?.totals?.total_price) {
                    return;
                }

                const cartTotal = parseInt(cartData.totals.total_price, 10) /
                    Math.pow(10, cartData.totals.currency_minor_unit);
                const currentCustomerType = getCustomerType();

                // Only update if amount or customer type changed.
                if (cartTotal !== previousAmount || currentCustomerType !== previousCustomerType) {
                    previousAmount = cartTotal;
                    previousCustomerType = currentCustomerType;
                    updateRwsContext(cartTotal);
                }
            } catch (e) {
                // Ignore errors during subscription.
            }
        });
    }

    // Register payment methods, making them available in the checkout.
    settings.payment_methods.forEach((method: any) => {
        /**
         * Calculate the cart total.
         *
         * @param cartData The cart data from the store.
         * @returns The cart total.
         */
        const calculateCartTotal = (cartData: any): number => {
            return (
                parseInt(cartData.totals.total_price, 10) /
                Math.pow(10, cartData.totals.currency_minor_unit)
            );
        };

        /**
         * Update the iframe source with the new cart total.
         *
         * @param iframe The iframe element to update.
         * @param cartTotal The new cart total.
         */
        const updateIframeSource = (
            iframe: HTMLIFrameElement,
            cartTotal: number
        ): void => {
            let src = iframe.getAttribute('src');
            if (src) {
                const lastEqualIndex = src.lastIndexOf('=');
                if (lastEqualIndex !== -1) {
                    src = src.substring(0, lastEqualIndex + 1) + cartTotal;
                    iframe.setAttribute('src', src);
                }
            }
        };

        const costlistCache: Record<string, string> = {};

        /**
         * Payment method content.
         *
         * Renders RWS custom elements when session token and rws_type are available,
         * otherwise falls back to the existing MAPI-based rendering.
         */
        const Content = () => {
            const cartData = select(CART_STORE_KEY).getCartData();
            const customerData = select(CART_STORE_KEY).getCustomerData();
            const cartTotal = calculateCartTotal(cartData);

            // Use RWS elements only when both token and rws_type are available.
            const useRwsElements = settings.rws_session_token && method.rws_type;

            if (useRwsElements) {
                return (
                    <div>
                        <resurs-payment-method-subtitle
                            type={method.rws_type}
                            amount={String(cartTotal)}
                        />
                        <resurs-payment-method
                            type={method.rws_type}
                            token={settings.rws_session_token}
                            amount={String(cartTotal)}
                        />
                    </div>
                );
            }

            // Fallback: use existing MAPI-based rendering.
            const billingCountry = customerData?.billingAddress?.country || '';
            const shippingCountry = customerData?.shippingAddress?.country || '';

            const [costlist, setCostlist] = React.useState(
                costlistCache[method.name] || method.costlist
            );

            React.useEffect(() => {
                const iframe = document.querySelector(
                    'iframe.rb-rm-iframe'
                ) as HTMLIFrameElement;

                if (iframe) {
                    updateIframeSource(iframe, cartTotal);
                }

                // Guard cart total updates and update costlist via local backend.
                fetch(`${method.costlist_url}&method=${method.name}&amount=${cartTotal}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.html && data.html !== costlist) {
                            costlistCache[method.name] = data.html;
                            setCostlist(data.html);
                        }
                    })
                    .catch(err => {
                        console.error("Failed to fetch costlist:", err);
                    });
            }, [cartTotal]);

            return (
                <div>
                    <div
                        dangerouslySetInnerHTML={{
                            __html: method.description,
                        }}
                    />
                    <div
                        dangerouslySetInnerHTML={{
                            __html: costlist,
                        }}
                    />
                    <div
                        dangerouslySetInnerHTML={{
                            __html: method.readmore,
                        }}
                    />
                    {(billingCountry === 'SE' || shippingCountry === 'SE') && (
                        <div
                            dangerouslySetInnerHTML={{
                                __html: method.price_signage_warning,
                            }}
                        />
                    )}
                </div>
            );
        };

        /**
         * Label component
         *
         * Renders RWS custom elements when session token and rws_type are available,
         * otherwise falls back to the existing MAPI-based rendering.
         *
         * @param {*} props Props from payment API.
         */
        const Label = (props: any) => {
            const {PaymentMethodLabel} = props.components;

            // Use RWS elements only when both token and rws_type are available.
            const useRwsElements = settings.rws_session_token && method.rws_type;

            if (useRwsElements) {
                return (
                    <div className="rb-payment-method-title">
                        <resurs-payment-method-title type={method.rws_type} />
                        <resurs-payment-method-icon type={method.rws_type} />
                    </div>
                );
            }

            // Fallback: use existing MAPI-based rendering.
            return (
                <div className="rb-payment-method-title">
                    <PaymentMethodLabel text={method.title}/>
                    <div
                        className={`rb-payment-method-logo rb-logo-type-${method.logo_type}`}
                        dangerouslySetInnerHTML={{__html: method.logo}}
                    />
                </div>
            );
        };

        registerPaymentMethod({
            name: method.name,
            paymentMethodId: method.name,
            label: <Label/>,
            content: <Content/>,
            edit: <Content/>,
            canMakePayment: (data: any) => {
                // List all properties and methods of the data object
                const cart_total =
                    parseInt(data.cartTotals.total_price, 10) /
                    Math.pow(10, data.cartTotals.currency_minor_unit);

                return Resursbank_PaymentMethod.isAvailable(
                    method.name,
                    cart_total,
                    ((document.getElementById('billing-company') as HTMLInputElement)?.value === '' ? 'NATURAL' : 'LEGAL'),
                    data.billingAddress.country
                );
            },
            ariaLabel: method.title,
            supports: {
                blockBasedCheckout: method.name !== 'resursbank',
                features: ['products', 'shipping', 'coupons'],
            },
        })
    });
})();
