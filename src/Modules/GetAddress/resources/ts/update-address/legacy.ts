// @ts-ignore
import * as jQuery from 'jquery';

// Ignore missing Resursbank_GetAddress renders through Ecom Widget.
declare const Resursbank_GetAddress: any;

// legacy.ts
export class LegacyAddressUpdater {
	private getAddressCustomerType: string | undefined;
	private getAddressWidget: any;

	constructor() {
		this.getAddressCustomerType = undefined;
		this.getAddressWidget = undefined;
	}

	initialize() {
		console.log( 'Legacy Updater' );

		jQuery( document ).on(
			'update_resurs_customer_type',
			( ev, customerType ) => {
				jQuery.ajax( {
					url: `${ rbCustomerTypeData[ 'apiUrl' ] }&customerType=${ customerType }`,
				} );
			}
		);

		jQuery( document ).ready( () => {
			if (
				typeof Resursbank_GetAddress !== 'function' ||
				document.getElementById( 'rb-ga-widget' ) === null
			) {
				return;
			}

			this.getAddressWidget = new Resursbank_GetAddress( {
				updateAddress: ( data: any ) => {
					this.getAddressCustomerType =
						this.getAddressWidget.getCustomerType();
					this.rbHandleFetchAddressResponse(
						data,
						this.getAddressCustomerType
					);
				},
			} );

			try {
				this.getAddressWidget.setupEventListeners();
				const naturalEl =
					this.getAddressWidget.getCustomerTypeElNatural();
				const legalEl = this.getAddressWidget.getCustomerTypeElLegal();
				naturalEl.addEventListener( 'change', () => {
					jQuery( 'body' ).trigger( 'update_resurs_customer_type', [
						'NATURAL',
					] );
				} );
				legalEl.addEventListener( 'change', () => {
					jQuery( 'body' ).trigger( 'update_resurs_customer_type', [
						'LEGAL',
					] );
				} );
			} catch ( e ) {
				console.log( e );
			}
		} );
	}

	private rbHandleFetchAddressResponse = ( () => {
		const getCheckoutForm = (): HTMLFormElement | null => {
			const form = document.forms[ 'checkout' ];
			return form instanceof HTMLFormElement ? form : null;
		};

		const getNamedFields = ( el: HTMLElement ): boolean =>
			el.hasAttribute( 'name' );
		const getBillingFields = ( el: HTMLInputElement ): boolean =>
			el.name.startsWith( 'billing' );
		const getShippingFields = ( el: HTMLInputElement ): boolean =>
			el.name.startsWith( 'shipping' );

		const mapResursFieldName = ( name: string ): string => {
			let result: string;

			switch (
				name.split( 'billing_' )[ 1 ] ||
				name.split( 'shipping_' )[ 1 ]
			) {
				case 'first_name':
					result = 'firstName';
					break;
				case 'last_name':
					result = 'lastName';
					break;
				case 'country':
					result = 'countryCode';
					break;
				case 'address_1':
					result = 'addressRow1';
					break;
				case 'address_2':
					result = 'addressRow2';
					break;
				case 'postcode':
					result = 'postalCode';
					break;
				case 'city':
					result = 'postalArea';
					break;
				case 'company':
					result = 'fullName';
					break;
				default:
					result = '';
			}

			return result;
		};

		const mapResursField = ( el: HTMLInputElement ) => ( {
			name: mapResursFieldName( el.name ),
			el,
		} );

		const getUsableFields = ( obj: { name: string } ): boolean =>
			obj.name !== '';

		const mapResursFields = (
			els: Element[]
		): { name: string; el: HTMLInputElement }[] =>
			els.map( mapResursField ).filter( getUsableFields );

		const getAddressFields = ( form: HTMLFormElement | null ) => {
			let result: {
				billing: { name: string; el: HTMLInputElement }[];
				shipping: { name: string; el: HTMLInputElement }[];
			} | null = null;
			if ( form instanceof HTMLFormElement ) {
				const arr = Array.from( form.elements );
				const namedFields = arr.filter( getNamedFields );

				result = {
					billing: mapResursFields(
						namedFields.filter( getBillingFields )
					),
					shipping: mapResursFields(
						namedFields.filter( getShippingFields )
					),
				};
			}

			return result;
		};

		const updateAddressFields = ( data: any, customerType: string ) => {
			const fields = getAddressFields( getCheckoutForm() );

			const billingResursGovId = jQuery(
				'#billing_resurs_government_id'
			);

			if (
				typeof this.getAddressWidget !== 'undefined' &&
				customerType === 'LEGAL'
			) {
				const govIdElement = this.getAddressWidget.getGovIdElement();
				if ( billingResursGovId.length > 0 ) {
					billingResursGovId.val( govIdElement.value );
				}
			}

			fields?.billing.forEach( ( obj ) => {
				const dataVal = data[ obj.name ];
				const newVal =
					typeof dataVal === 'string' ? dataVal : obj.el.value;
				if ( obj.name === 'fullName' ) {
					if ( customerType === 'LEGAL' ) {
						obj.el.value = newVal;
					} else {
						obj.el.value = '';
						billingResursGovId.val( '' );
					}
				} else {
					obj.el.value = newVal;
				}

				if (
					typeof obj.el.parentNode?.parentNode?.classList ===
						'object' &&
					obj.el.parentNode.parentNode.classList.contains(
						'woocommerce-invalid'
					)
				) {
					obj.el.parentNode.parentNode.classList.remove(
						'woocommerce-invalid'
					);
					obj.el.parentNode.parentNode.classList.remove(
						'woocommerce-invalid-required-field'
					);
				}
			} );
		};

		const billingResursGovId = jQuery( '#billing_resurs_government_id' );

		return ( data: any, customerType: string ) => {
			try {
				if ( billingResursGovId.length > 0 ) {
					if ( customerType === 'LEGAL' ) {
						if (
							jQuery(
								'#rb-customer-widget-getAddress-input-govId'
							).length > 0
						) {
							billingResursGovId.val(
								jQuery(
									'#rb-customer-widget-getAddress-input-govId'
								).val()
							);
						}
					} else {
						billingResursGovId.val( '' );
					}
				}
				updateAddressFields( data, customerType );
				rbUpdateCustomerType();
			} catch ( e ) {
				console.log( e );
			}
		};
	} )();
}
