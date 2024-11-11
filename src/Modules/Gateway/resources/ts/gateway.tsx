import { __ } from '@wordpress/i18n';
// @ts-ignore
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
// @ts-ignore
import { getSetting } from '@woocommerce/settings';
import React from "react";

const settings = getSetting( 'resursbank_data', {} );

// Log the entire state of the stores
/*const logStoreData = () => {
    const storeData = select('core').getStore();
    console.log('Store Data:', storeData);
};

// Call the function to log the store data
logStoreData();*/

/*const updateCartTotal = () => {
    console.log('asdasdasdasd');
    //console.log('oboy!');
    /*const cart = select('wc/store/cart').getCart();
    if (cart) {
        cart_total = cart.totals.total_price / Math.pow(10, cart.totals.currency_minor_unit);
    }*/
/*};*/

const storeName = window.wc.wcBlocksData.CART_STORE_KEY;

if (Array.isArray(settings.payment_methods) && settings.payment_methods.length > 0) {
    settings.payment_methods.forEach((method: any) => {
        const label = method.title;

        /**
         * Content component
         */
        const Content = () => {
            const div = document.createElement('div');
            const style = document.createElement('style');
            style.textContent = method.read_more_css;

            const total_price = wp.data.select(storeName).getCartData().totals.total_price;
            const currency_minor_unit = wp.data.select(storeName).getCartData().totals.currency_minor_unit;
            const cart_total = parseInt(total_price, 10) / Math.pow(10, currency_minor_unit);

            div.innerHTML = method.description;

            const iframe = div.querySelector('iframe.rb-rm-iframe');
            if (iframe) {
                let src = iframe.getAttribute('src');
                if (src) {
                    const lastEqualIndex = src.lastIndexOf('=');
                    if (lastEqualIndex !== -1) {
                        src = src.substring(0, lastEqualIndex + 1);
                        src += cart_total;
                        iframe.setAttribute('src', src);
                    }
                }
            }

            div.appendChild(style);

            /*
            * {method.enabled_for_legal_customer && (
                        <div>
                            <label htmlFor="billing_resurs_government_id">Government ID:</label>
                            <ValidatedTextInput
                                id="billing_resurs_government_id"
                                type="number"
                                required={false}
                                className={'government-id'}
                                label={'Government ID'}
                                value={governmentId}
                            />
                            {error && <p style={{ color: 'red' }}>{error}</p>}
                        </div>
                    )}*/

            return (
                <div>
                    <div dangerouslySetInnerHTML={{ __html: div.innerHTML }} />
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
            // @ts-ignore
            const labelWithLogo = (
                <div className="rb-payment-method-title">
                    <PaymentMethodLabel text={label}/>
                    <div className={`rb-payment-method-logo rb-logo-type-${method.logo_type}`}
                         dangerouslySetInnerHTML={{__html: method.logo}}/>
                </div>
            );
            return labelWithLogo;
        };

        registerPaymentMethod({
            name: method.name,
            label: <Label/>,
            content: <Content/>,
            edit: <Content/>,
            canMakePayment: (data: any) => {
                // Filter out all payment methods if customer country does not
                // match country associated with API account.
                if (data.billingAddress.country !== settings.allowed_country) {
                    return false;
                }

                // Filter out payment methods based on customer type (determined
                // by whether a company name is provided).
                if (
                    (data.billingAddress.company !== '' && !method.enabled_for_legal_customer) ||
                    (data.billingAddress.company === '' && !method.enabled_for_natural_customer)
                ) {
                    return false;
                }

                // List all properties and methods of the dat)a object
                const cart_total = parseInt(data.cartTotals.total_price, 10) / Math.pow(10, data.cartTotals.currency_minor_unit);

                // Filter out payment methods based on min / max order total.
                if (
                    cart_total < method.min_purchase_limit ||
                    cart_total > method.max_purchase_limit
                ) {
                    return false;
                }

                // All checks passed, payment method is allowed.
                return true;
            },
            ariaLabel: label
        });
    });
}
