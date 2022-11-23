const rbHandleFetchAddressResponse = (() => {
    /**
     * @namespace Rb
     */

    /**
     * @namespace Rb.GetAddress
     */

    /**
     * @memberOf Rb.GetAddress
     * @name MappedAddressEl
     * @typedef {object}
     * @property {string} name
     * @property {HTMLInputElement} el
     */

    /**
     * @memberOf Rb.GetAddress
     * @name AddressFields
     * @typedef {object}
     * @property {Rb.GetAddress.MappedAddressEl[]} billing
     * @property {Rb.GetAddress.MappedAddressEl[]} shipping
     */

    /**
     * @memberOf Rb
     * @name Address
     * @typedef {object}
     * @property {string|null} addressRow1
     * @property {string|null} addressRow2
     * @property {string|null} countryCode
     * @property {string|null} firstName
     * @property {string|null} fullName
     * @property {string|null} lastName
     * @property {string|null} postalArea
     * @property {string|null} postalCode
     * @property {string|null} addressRow1
     */

    /**
     * @returns {HTMLFormElement|null}
     */
    const getCheckoutForm = () => {
        const form = document.forms['checkout'];

        return form instanceof HTMLFormElement ? form : null;
    };

    /**
     * A filter to get elements with the "name" attribute.
     *
     * @param {HTMLElement} el
     * @returns {boolean}
     */
    const getNamedFields = (el) => el.hasAttribute('name');

    /**
     * A filter to get elements whose `name` starts with `"billing"`.
     *
     * @param {HTMLInputElement} el
     * @returns {boolean}
     */
    const getBillingFields = (el) => el.name.startsWith('billing');

    /**
     * A filter to get elements whose `name` starts with `"shipping"`.
     *
     * @param {HTMLInputElement} el
     * @returns {boolean}
     */
    const getShippingFields = (el) => el.name.startsWith('shipping');

    /**
     * Maps an address field `name` to the Resurs Bank address model
     * equivalent. The mapped names are taken from the data of the returned
     * response when fetching a customer address.
     *
     * @param {string} name
     * @returns {string}
     */
    const mapResursFieldName = (name) => {
        let result;

        switch (name.split('billing_')[1] || name.split('shipping_')[1]) {
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
            default:
                result = '';
        }

        return result;
    }

    /**
     * Maps an address field element to an object which includes the element
     * and its `name` which has been mapped to a Resurs Bank equivalent.
     *
     * @param {HTMLInputElement} el
     * @returns {Rb.GetAddress.MappedAddressEl}
     */
    const mapResursField = (el) => ({ name: mapResursFieldName(el.name), el });

    /**
     * A filter to remove address fields that are not used by Resurs Bank.
     *
     * @param {Rb.GetAddress.MappedAddressEl} obj
     * @returns {boolean}
     */
    const getUsableFields = (obj) => obj.name !== '';

    /**
     * Maps an array of elements to an array of
     *
     * @param {Element[]} els
     * @returns {Rb.GetAddress.MappedAddressEl[]}
     */
    const mapResursFields = (els) =>
        els.map(mapResursField).filter(getUsableFields);

    /**
     * Gathers and returns an object with both billing and shipping address
     * fields. Each section is a list with {@see Rb.GetAddress.MappedAddressEl}
     * values.
     *
     * @param {HTMLFormElement|null} form
     * @return {null|Rb.GetAddress.AddressFields}
     */
    const getAddressFields = (form) => {
        let result = null;

        if (form instanceof HTMLFormElement) {
            const arr = Array.from(form.elements);
            const namedFields = arr.filter(getNamedFields);

            result = {};
            result.billing = mapResursFields(
                namedFields.filter(getBillingFields)
            );
            result.shipping = mapResursFields(
                namedFields.filter(getShippingFields)
            );
        }

        return result;
    }

    /**
     * Updates checkout address fields with the supplied address data.
     *
     * @param {Rb.Address} data
     */
    const updateAddressFields = (data) => {
        const fields = getAddressFields(getCheckoutForm());

        fields?.billing.forEach((obj) => {
            const newVal = data[obj.name];

            obj.el.value = typeof newVal === 'string' ? newVal : obj.el.value;
        });
    };

    return (data) => {
        console.log('rbHandleFetchAddressResponse:', data);

        try {
            updateAddressFields(data);
        } catch (e) {
            console.log(e);
        }
    };
})();
