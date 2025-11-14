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
         */
        const Content = () => {
            const cartData = select(CART_STORE_KEY).getCartData();
            const customerData = select(CART_STORE_KEY).getCustomerData();
            const cartTotal = calculateCartTotal(cartData);

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
         * @param {*} props Props from payment API.
         */
        const Label = (props: any) => {
            const {PaymentMethodLabel} = props.components;

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
