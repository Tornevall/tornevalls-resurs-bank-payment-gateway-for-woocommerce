/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/Modules/GetAddress/resources/ts/update-address/blocks.ts":
/*!**********************************************************************!*\
  !*** ./src/Modules/GetAddress/resources/ts/update-address/blocks.ts ***!
  \**********************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   BlocksAddressUpdater: () => (/* binding */ BlocksAddressUpdater)
/* harmony export */ });
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _woocommerce_block_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @woocommerce/block-data */ "@woocommerce/block-data");
/* harmony import */ var _woocommerce_block_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_woocommerce_block_data__WEBPACK_IMPORTED_MODULE_1__);

// @ts-ignore


// Ignore missing Resursbank_GetAddress renders through Ecom Widget.

class BlocksAddressUpdater {
  /**
   * Widget instance.
   */
  widget = null;

  /**
   * Generate widget instance.
   */
  constructor() {
    // Initialize any properties if needed
    this.widget = new Resursbank_GetAddress({
      updateAddress: data => {
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
          company: 'fullName'
        };
        for (const [key, value] of Object.entries(map)) {
          if (!data.hasOwnProperty(value)) {
            throw new Error(`Missing required field "${value}" in data object.`);
          }
          if (key === 'company') {
            if (typeof data[value] === 'string' && this.widget.getCustomerType() === 'LEGAL') {
              cartData.shippingAddress.company = data[value];
              continue;
            }
            continue;
          }
          cartData.shippingAddress[key] = typeof data[value] === 'string' ? data[value] : '';
        }

        // Dispatch the updated cart data back to the store
        (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.dispatch)(_woocommerce_block_data__WEBPACK_IMPORTED_MODULE_1__.CART_STORE_KEY).setCartData(cartData);
      }
    });
  }

  /**
   * Configure the event listeners for the widget.
   */
  initialize() {
    this.widget.setupEventListeners();
  }

  /**
   * Resolve cart data from store and confirm the presence of shipping address
   * data since this is what we will be manipulating.
   */
  getCartData() {
    const data = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.select)(_woocommerce_block_data__WEBPACK_IMPORTED_MODULE_1__.CART_STORE_KEY).getCartData();

    // Validate presence of shippingAddress and all required fields.
    if (!data.shippingAddress) {
      throw new Error('Missing shipping address data in cart.');
    }

    // Loop through all required fields and ensure they are present.
    const requiredFields = ['first_name', 'last_name', 'address_1', 'address_2', 'postcode', 'city', 'country', 'company'];
    for (const field of requiredFields) {
      if (data.shippingAddress[field] === undefined) {
        throw new Error(`Missing required field "${field}" in shipping address data.`);
      }
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
    (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.dispatch)(_woocommerce_block_data__WEBPACK_IMPORTED_MODULE_1__.CART_STORE_KEY).setCartData(cartData);
  }
}

/***/ }),

/***/ "./src/Modules/GetAddress/resources/ts/update-address/legacy.ts":
/*!**********************************************************************!*\
  !*** ./src/Modules/GetAddress/resources/ts/update-address/legacy.ts ***!
  \**********************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   LegacyAddressUpdater: () => (/* binding */ LegacyAddressUpdater)
/* harmony export */ });
/* harmony import */ var jquery__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! jquery */ "jquery");
/* harmony import */ var jquery__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(jquery__WEBPACK_IMPORTED_MODULE_0__);
// @ts-ignore


// Ignore missing Resursbank_GetAddress renders through Ecom Widget.

// legacy.ts
class LegacyAddressUpdater {
  constructor() {
    this.getAddressCustomerType = undefined;
    this.getAddressWidget = undefined;
  }
  initialize() {
    console.log('Legacy Updater');
    jquery__WEBPACK_IMPORTED_MODULE_0__(document).on('update_resurs_customer_type', (ev, customerType) => {
      jquery__WEBPACK_IMPORTED_MODULE_0__.ajax({
        url: `${rbCustomerTypeData['apiUrl']}&customerType=${customerType}`
      });
    });
    jquery__WEBPACK_IMPORTED_MODULE_0__(document).ready(() => {
      if (typeof Resursbank_GetAddress !== 'function' || document.getElementById('rb-ga-widget') === null) {
        return;
      }
      this.getAddressWidget = new Resursbank_GetAddress({
        updateAddress: data => {
          this.getAddressCustomerType = this.getAddressWidget.getCustomerType();
          this.rbHandleFetchAddressResponse(data, this.getAddressCustomerType);
        }
      });
      try {
        this.getAddressWidget.setupEventListeners();
        const naturalEl = this.getAddressWidget.getCustomerTypeElNatural();
        const legalEl = this.getAddressWidget.getCustomerTypeElLegal();
        naturalEl.addEventListener('change', () => {
          jquery__WEBPACK_IMPORTED_MODULE_0__('body').trigger('update_resurs_customer_type', ['NATURAL']);
        });
        legalEl.addEventListener('change', () => {
          jquery__WEBPACK_IMPORTED_MODULE_0__('body').trigger('update_resurs_customer_type', ['LEGAL']);
        });
      } catch (e) {
        console.log(e);
      }
    });
  }
  rbHandleFetchAddressResponse = (() => {
    const getCheckoutForm = () => {
      const form = document.forms['checkout'];
      return form instanceof HTMLFormElement ? form : null;
    };
    const getNamedFields = el => el.hasAttribute('name');
    const getBillingFields = el => el.name.startsWith('billing');
    const getShippingFields = el => el.name.startsWith('shipping');
    const mapResursFieldName = name => {
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
        case 'company':
          result = 'fullName';
          break;
        default:
          result = '';
      }
      return result;
    };
    const mapResursField = el => ({
      name: mapResursFieldName(el.name),
      el
    });
    const getUsableFields = obj => obj.name !== '';
    const mapResursFields = els => els.map(mapResursField).filter(getUsableFields);
    const getAddressFields = form => {
      let result = null;
      if (form instanceof HTMLFormElement) {
        const arr = Array.from(form.elements);
        const namedFields = arr.filter(getNamedFields);
        result = {
          billing: mapResursFields(namedFields.filter(getBillingFields)),
          shipping: mapResursFields(namedFields.filter(getShippingFields))
        };
      }
      return result;
    };
    const updateAddressFields = (data, customerType) => {
      const fields = getAddressFields(getCheckoutForm());
      const billingResursGovId = jquery__WEBPACK_IMPORTED_MODULE_0__('#billing_resurs_government_id');
      if (typeof this.getAddressWidget !== 'undefined' && customerType === 'LEGAL') {
        const govIdElement = this.getAddressWidget.getGovIdElement();
        if (billingResursGovId.length > 0) {
          billingResursGovId.val(govIdElement.value);
        }
      }
      fields?.billing.forEach(obj => {
        const dataVal = data[obj.name];
        const newVal = typeof dataVal === 'string' ? dataVal : obj.el.value;
        if (obj.name === 'fullName') {
          if (customerType === 'LEGAL') {
            obj.el.value = newVal;
          } else {
            obj.el.value = '';
            billingResursGovId.val('');
          }
        } else {
          obj.el.value = newVal;
        }
        if (typeof obj.el.parentNode?.parentNode?.classList === 'object' && obj.el.parentNode.parentNode.classList.contains('woocommerce-invalid')) {
          obj.el.parentNode.parentNode.classList.remove('woocommerce-invalid');
          obj.el.parentNode.parentNode.classList.remove('woocommerce-invalid-required-field');
        }
      });
    };
    const billingResursGovId = jquery__WEBPACK_IMPORTED_MODULE_0__('#billing_resurs_government_id');
    return (data, customerType) => {
      try {
        if (billingResursGovId.length > 0) {
          if (customerType === 'LEGAL') {
            if (jquery__WEBPACK_IMPORTED_MODULE_0__('#rb-customer-widget-getAddress-input-govId').length > 0) {
              billingResursGovId.val(jquery__WEBPACK_IMPORTED_MODULE_0__('#rb-customer-widget-getAddress-input-govId').val());
            }
          } else {
            billingResursGovId.val('');
          }
        }
        updateAddressFields(data, customerType);
        rbUpdateCustomerType();
      } catch (e) {
        console.log(e);
      }
    };
  })();
}

/***/ }),

/***/ "jquery":
/*!*************************!*\
  !*** external "jQuery" ***!
  \*************************/
/***/ ((module) => {

module.exports = window["jQuery"];

/***/ }),

/***/ "@woocommerce/block-data":
/*!**************************************!*\
  !*** external ["wc","wcBlocksData"] ***!
  \**************************************/
/***/ ((module) => {

module.exports = window["wc"]["wcBlocksData"];

/***/ }),

/***/ "@wordpress/data":
/*!******************************!*\
  !*** external ["wp","data"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["data"];

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be isolated against other modules in the chunk.
(() => {
/*!***************************************************************!*\
  !*** ./src/Modules/GetAddress/resources/ts/update-address.ts ***!
  \***************************************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _update_address_legacy__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./update-address/legacy */ "./src/Modules/GetAddress/resources/ts/update-address/legacy.ts");
/* harmony import */ var _update_address_blocks__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./update-address/blocks */ "./src/Modules/GetAddress/resources/ts/update-address/blocks.ts");



// Ignore missing Resursbank_GetAddress renders through Ecom Widget.

/**
 * We use different JS code to update the address fields depending on if the
 * theme uses blocks or not. This script will initialize the correct script
 * depending on whether blocks or legacy is used.
 */
document.addEventListener('DOMContentLoaded', () => {
  // Confirm we are loaded on the checkout page.
  if (!document.querySelector('.woocommerce-checkout')) {
    return;
  }

  // Confirm that the Resursbank_GetAddress function is available.
  if (typeof Resursbank_GetAddress !== 'function') {
    return;
  }

  // Initialize blocks.
  if (document.querySelector('.wc-block-components-form')) {
    new _update_address_blocks__WEBPACK_IMPORTED_MODULE_1__.BlocksAddressUpdater().initialize();
    return;
  }

  // Initialize legacy.
  new _update_address_legacy__WEBPACK_IMPORTED_MODULE_0__.LegacyAddressUpdater().initialize();
});
})();

/******/ })()
;
//# sourceMappingURL=update-address.js.map