// @ts-ignore
import * as jQuery from 'jquery';

// Declare the Resursbank_GetAddress function from the external library.
declare const Resursbank_GetAddress: any;

// LegacyAddressUpdater class handles interactions with the Resursbank address widget
export class LegacyAddressUpdater {
	private getAddressWidget: any;

	constructor() {
		// Initialize the address widget to undefined.
		this.getAddressWidget = undefined;
	}

	/**
	 * Update the customer type in the checkout process.
	 * Sends an AJAX request to update the customer type and triggers the checkout update event.
	 * @param customerType The type of customer (LEGAL or NATURAL).
	 */
	private updateCustomerType(customerType: string) { // @ts-ignore
		// rbCustomerTypeData is expected through internal localization.
		const apiUrl = rbCustomerTypeData?.apiUrl; // Ensure the API URL is defined.
		if (!apiUrl) {
			console.error('API URL is undefined');
			return;
		}

		jQuery.ajax({
			url: `${apiUrl}&customerType=${customerType}`,
		})
			.done(() => {
				// Trigger the update_checkout event on successful AJAX call.
				jQuery(document.body).trigger('update_checkout');
			}) // @ts-ignore
			.fail((error) => {
				// Log any errors encountered during the AJAX call.
				console.error('Error updating customer type:', error);
			});
	}

	/**
	 * Initialize the LegacyAddressUpdater.
	 * Sets up the address widget and event listeners for handling updates.
	 */
	initialize() {
		console.log('Legacy Address Fetcher Loaded.');

		jQuery(document).ready(() => {
			// Ensure the address widget is available before proceeding.
			if (typeof Resursbank_GetAddress !== 'function' || !document.getElementById('rb-ga-widget')) {
				return;
			}

			// Initialize the address widget with updateAddress callback.
			this.getAddressWidget = new Resursbank_GetAddress({
				updateAddress: (data: any) => {
					// Handle the fetched address response and update the customer type.
					this.handleFetchAddressResponse(data);
					this.updateCustomerType(this.getAddressWidget.getCustomerType());
				},
			});

			try {
				// Set up customer type on initialization.
				this.setupCustomerTypeOnInit();

				// Configure event listeners for the widget.
				this.getAddressWidget.setupEventListeners();
			} catch (error) {
				console.error('Error initializing address widget:', error);
			}
		});
	}

	/**
	 * Configure the initial customer type based on the billing company field.
	 */
	private setupCustomerTypeOnInit() {
		const billingCompany = jQuery('#billing_company');
		const isCompany = billingCompany.length > 0 && billingCompany.val() !== '';
		const customerType = isCompany ? 'LEGAL' : 'NATURAL';

		const naturalEl = this.getAddressWidget.getCustomerTypeElNatural();
		const legalEl = this.getAddressWidget.getCustomerTypeElLegal();

		// Update the customer type based on initial conditions.
		this.updateCustomerType(customerType);
		if (customerType === 'LEGAL') {
			legalEl.checked = true;
		} else {
			naturalEl.checked = true;
		}
	}

	/**
	 * Handle the response from the address widget and map the fields.
	 * This function updates the checkout form fields with the retrieved address data.
	 */
	private handleFetchAddressResponse = (() => {
		/**
		 * Retrieve the checkout form element.
		 * @returns The checkout form element or null if not found.
		 */
		const getCheckoutForm = (): HTMLFormElement | null => { // @ts-ignore
			const form = document.forms['checkout'];
			return form instanceof HTMLFormElement ? form : null;
		};

		/**
		 * Map field names from Resursbank format to WooCommerce field names.
		 * @param name The field name to map.
		 * @returns The mapped field name or an empty string if not mapped.
		 */
		const mapFieldNames = {
			first_name: 'firstName',
			last_name: 'lastName',
			country: 'countryCode',
			address_1: 'addressRow1',
			address_2: 'addressRow2',
			postcode: 'postalCode',
			city: 'postalArea',
			company: 'fullName',
		};

		const mapResursFieldName = (name: string): string => {
			const key = name.split('_')[1]; // @ts-ignore
			return mapFieldNames[key] || '';
		};

		/**
		 * Map form elements to Resurs field format.
		 * @param elements The form elements to map.
		 * @returns An array of objects containing name and element pairs.
		 */
		const mapFields = (elements: HTMLInputElement[]): { name: string; el: HTMLInputElement }[] => {
			return elements
				.map((el) => ({ name: mapResursFieldName(el.name), el }))
				.filter((field) => field.name !== '');
		};

		/**
		 * Get address fields from the checkout form.
		 * @param form The checkout form element.
		 * @returns An object containing billing and shipping fields or null if the form is invalid.
		 */
		const getAddressFields = (form: HTMLFormElement | null) => {
			if (!form) return null;

			const elements = Array.from(form.elements) as HTMLInputElement[];
			const namedFields = elements.filter((el) => el.name);

			return {
				billing: mapFields(namedFields.filter((el) => el.name.startsWith('billing_'))),
				shipping: mapFields(namedFields.filter((el) => el.name.startsWith('shipping_'))),
			};
		};

		/**
		 * Update form fields with the provided data.
		 * @param fields The fields to update.
		 * @param data The data to use for updating the fields.
		 */
		const updateFields = (fields: { name: string; el: HTMLInputElement }[], data: any) => {
			fields.forEach(({ name, el }) => {
				const value = data[name] ?? el.value;

				// Handle fullName updates for LEGAL customer type.
				if (name === 'fullName' && this.getAddressWidget.getCustomerType() !== 'LEGAL') {
					el.value = '';
				} else {
					el.value = value;
				}

				// Remove invalid class indicators from fields.
				const parentNode = el.closest('.woocommerce-invalid');
				if (parentNode) {
					parentNode.classList.remove('woocommerce-invalid', 'woocommerce-invalid-required-field');
				}
			});
		};

		return (data: any) => {
			try {
				const fields = getAddressFields(getCheckoutForm());
				if (fields) {
					updateFields(fields.billing, data);
				}
			} catch (error) {
				console.error('Error updating address fields:', error);
			}
		};
	})();
}
