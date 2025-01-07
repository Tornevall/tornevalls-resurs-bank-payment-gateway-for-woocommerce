(()=>{"use strict";const e=window.jQuery;class t{constructor(){this.getAddressEnabled="1"===rbFrontendData?.getAddressEnabled||!0===rbFrontendData?.getAddressEnabled,this.getAddressWidget=void 0}updateCustomerType(t){const s=rbFrontendData?.apiUrl;s?e.ajax({url:`${s}&customerType=${t}`}).done((()=>{e(document.body).trigger("update_checkout")})).fail((e=>{console.error("Error updating customer type:",e)})):console.error("API URL is undefined")}initialize(){this.getAddressEnabled?(console.log("Legacy Address Fetcher Loaded."),e(document).ready((()=>{if("function"==typeof Resursbank_GetAddress&&document.getElementById("rb-ga-widget")){this.getAddressWidget=new Resursbank_GetAddress({updateAddress:e=>{this.handleFetchAddressResponse(e),this.updateCustomerType(this.getAddressWidget.getCustomerType())}});try{this.setupCustomerTypeOnInit(),this.getAddressWidget.setupEventListeners()}catch(e){console.error("Error initializing address widget:",e)}}}))):console.log("Legacy Address Fetcher is disabled.")}isCorporate(){const t=e("#billing_company");return t.length>0&&""!==t.val().trim()}setupCustomerTypeOnInit(){const t=this.getAddressWidget.getCustomerTypeElNatural(),s=this.getAddressWidget.getCustomerTypeElLegal();this.isCorporate()?s.checked=!0:t.checked=!0;const a=()=>{const e=this.isCorporate()?"LEGAL":"NATURAL";this.updateCustomerType(e)};a(),e("#billing_company").on("input change",a)}handleFetchAddressResponse=(()=>{const e=e=>{let t="";switch(e.split("billing_")[1]||e.split("shipping_")[1]){case"first_name":t="firstName";break;case"last_name":t="lastName";break;case"country":t="countryCode";break;case"address_1":t="addressRow1";break;case"address_2":t="addressRow2";break;case"postcode":t="postalCode";break;case"city":t="postalArea";break;case"company":t="fullName";break;default:t=""}return t},t=t=>t.map((t=>({name:e(t.name),el:t}))).filter((e=>""!==e.name)),s=(e,t)=>{e.forEach((({name:e,el:s})=>{var a;const r=null!==(a=t[e])&&void 0!==a?a:s.value;"fullName"===e&&"LEGAL"!==this.getAddressWidget.getCustomerType()?s.value="":s.value=r;const n=s.closest(".woocommerce-invalid");n&&n.classList.remove("woocommerce-invalid","woocommerce-invalid-required-field")}))};return e=>{try{const a=(e=>{if(!e)return null;const s=Array.from(e.elements).filter((e=>e.name));return{billing:t(s.filter((e=>e.name.startsWith("billing_")))),shipping:t(s.filter((e=>e.name.startsWith("shipping_"))))}})((()=>{const e=document.forms.checkout;return e instanceof HTMLFormElement?e:null})());a&&s(a.billing,e)}catch(e){console.error("Error updating address fields:",e)}}})()}const s=window.wp.data,a=window.wc.wcBlocksData,r=window.wc.wcSettings;class n{widget=null;allPaymentMethods=[];constructor(){this.widget=new Resursbank_GetAddress({updateAddress:e=>{this.resetCartData();let t=this.getCartData();const r={first_name:"firstName",last_name:"lastName",address_1:"addressRow1",address_2:"addressRow2",postcode:"postalCode",city:"postalArea",country:"countryCode",company:"fullName"};for(const[s,a]of Object.entries(r)){if(!e.hasOwnProperty(a))throw new Error(`Missing required field "${a}" in data object.`);if("company"===s){this.setBillingAndShipping(t,"string"==typeof e[a]&&"LEGAL"===this.widget.getCustomerType()?e[a]:"");continue}const r="string"==typeof e[a]?e[a]:"";t.shippingAddress[s]=r,t.billingAddress[s]=r}(0,s.dispatch)(a.CART_STORE_KEY).setCartData(t),this.refreshPaymentMethods()}})}setBillingAndShipping(e,t){e.shippingAddress.company=t,e.billingAddress.company=t}initialize(){(0,s.select)(a.CART_STORE_KEY).hasFinishedResolution("getCartData")||(console.log("Cart data not ready, triggered dispatch."),(0,s.dispatch)(a.CART_STORE_KEY).invalidateResolution("getCartData")),this.widget.setupEventListeners(),this.loadAllPaymentMethods(),this.refreshPaymentMethods()}loadAllPaymentMethods(){const e=(0,s.select)(a.CART_STORE_KEY).getCartData(),t=(0,r.getSetting)("resursbank_data",{}).payment_methods||[],n=new Set((e.paymentMethods||[]).map((e=>e.toLowerCase())));this.allPaymentMethods=[...e.paymentMethods||[]],t.forEach((e=>{const t=(e.id?.toLowerCase()||e.name?.toLowerCase()).trim();n.has(t)||this.allPaymentMethods.push(t)}))}getCartData(){const e=(0,s.select)(a.CART_STORE_KEY).getCartData();if(!e.shippingAddress||["first_name","last_name","address_1","address_2","postcode","city","country","company"].some((t=>void 0===e.shippingAddress[t])))throw new Error("Missing required shipping address data in cart.");return e}resetCartData(){let e=this.getCartData();e.shippingAddress.first_name="",e.shippingAddress.last_name="",e.shippingAddress.address_1="",e.shippingAddress.address_2="",e.shippingAddress.postcode="",e.shippingAddress.city="",e.shippingAddress.country="",e.shippingAddress.company="",(0,s.dispatch)(a.CART_STORE_KEY).setCartData(e)}refreshPaymentMethods(){if(!this.allPaymentMethods.length)return console.log("No payment methods available for filtering."),void this.loadAllPaymentMethods();const e=(0,s.select)(a.CART_STORE_KEY).getCartData();if(!e.paymentMethods)return console.warn("No payment methods found in cart data."),void(0,s.dispatch)(a.CART_STORE_KEY).invalidateResolution("getCartData");const t=(0,r.getSetting)("resursbank_data",{}).payment_methods||[],n=new Map(t.map((e=>[e.id?.toLowerCase()||e.name?.toLowerCase(),e]))),o="LEGAL"===this.widget.getCustomerType()||""!==e.billingAddress?.company?.trim(),i=parseInt(e.totals.total_price,10)/Math.pow(10,e.totals.currency_minor_unit),d=this.allPaymentMethods.map((e=>{const t=e?.toLowerCase().trim(),s=n.get(t);if(s){const{enabled_for_legal_customer:t,enabled_for_natural_customer:a,min_purchase_limit:r,max_purchase_limit:n}=s;return(o&&t||!o&&a||!o&&t&&a)&&i>=r&&i<=n?e:null}return e})).filter(Boolean);(0,s.dispatch)(a.CART_STORE_KEY).setCartData({...e,paymentMethods:d}),o&&setTimeout((()=>{this.widget.getCustomerTypeElLegal().checked=!0}),100)}}document.addEventListener("DOMContentLoaded",(()=>{"1"===rbFrontendData?.getAddressEnabled||!0===rbFrontendData?.getAddressEnabled?document.querySelector(".woocommerce-checkout")&&"function"==typeof Resursbank_GetAddress&&(document.querySelector(".wc-block-components-form")?(console.log("Fetcher found by element."),(new n).initialize()):new MutationObserver(((e,t)=>{document.querySelector(".wc-block-components-form")&&((new n).initialize(),console.log("Fetcher found by observer."),t.disconnect())})).observe(document.body,{childList:!0,subtree:!0}),(new t).initialize()):console.log("Address Fetcher is disabled.")}))})();