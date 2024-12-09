import React from 'react';
import { select } from '@wordpress/data';

// @ts-ignore
import { CART_STORE_KEY } from '@woocommerce/block-data';
// @ts-ignore
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
// @ts-ignore
import { getSetting } from '@woocommerce/settings';

const settings = getSetting( 'resursbank_data', {} );

( () => {
	if (
		! Array.isArray( settings.payment_methods ) ||
		settings.payment_methods.length === 0
	) {
		return;
	}

	settings.payment_methods.forEach( ( method: any ) => {
		const label = method.title;

		/**
		 * Calculate the cart total.
		 *
		 * @param cartData The cart data from the store.
		 * @returns The cart total.
		 */
		const calculateCartTotal = ( cartData: any ): number => {
			return (
				parseInt( cartData.totals.total_price, 10 ) /
				Math.pow( 10, cartData.totals.currency_minor_unit )
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
			let src = iframe.getAttribute( 'src' );
			if ( src ) {
				const lastEqualIndex = src.lastIndexOf( '=' );
				if ( lastEqualIndex !== -1 ) {
					src = src.substring( 0, lastEqualIndex + 1 ) + cartTotal;
					iframe.setAttribute( 'src', src );
				}
			}
		};

		/**
		 * Content component
		 */
		const Content = () => {
			const cartData = select( CART_STORE_KEY ).getCartData();
			const cartTotal = calculateCartTotal( cartData );

			React.useEffect( () => {
				const iframe = document.querySelector(
					'iframe.rb-rm-iframe'
				) as HTMLIFrameElement;
				if ( iframe ) {
					updateIframeSource( iframe, cartTotal );
				}
			}, [ cartTotal ] );

			return (
				<div>
					<div
						dangerouslySetInnerHTML={ {
							__html: method.description,
						} }
					/>
					<style>{ method.read_more_css }</style>
				</div>
			);
		};

		/**
		 * Label component
		 *
		 * @param {*} props Props from payment API.
		 */
		const Label = ( props: any ) => {
			const { PaymentMethodLabel } = props.components;

			return (
				<div className="rb-payment-method-title">
					<PaymentMethodLabel text={ label } />
					<div
						className={ `rb-payment-method-logo rb-logo-type-${ method.logo_type }` }
						dangerouslySetInnerHTML={ { __html: method.logo } }
					/>
				</div>
			);
		};

		registerPaymentMethod( {
			name: method.name,
			label: <Label />,
			content: <Content />,
			edit: <Content />,
			canMakePayment: ( data: any ) => {
				// Filter out all payment methods if customer country does not
				// match country associated with API account.
				if (
					data.billingAddress.country !== settings.allowed_country
				) {
					return false;
				}

				// Filter out payment methods based on customer type (determined
				// by whether a company name is provided).
				if (
					( data.billingAddress.company !== '' &&
						! method.enabled_for_legal_customer ) ||
					( data.billingAddress.company === '' &&
						! method.enabled_for_natural_customer
					)
				) {
					return false;
				}

				// List all properties and methods of the data object
				const cart_total =
					parseInt( data.cartTotals.total_price, 10 ) /
					Math.pow( 10, data.cartTotals.currency_minor_unit );

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
			ariaLabel: label,
		} );
	} );
} )();
