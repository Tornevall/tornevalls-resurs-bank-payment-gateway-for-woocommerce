(()=>{"use strict";const e=window.jQuery;class t{updateCustomerType(t){const s=rbFrontendData?.apiUrl;s?(resursConsoleLog("Customer Type Update: "+t,"DEBUG"),e.ajax({url:`${s}&customerType=${t}`}).done((t=>{resursConsoleLog("Updated customer: "+t?.customerType,"DEBUG"),e(document.body).trigger("update_checkout")})).fail((e=>{console.error("Error updating customer type:",e)}))):console.error("API URL is undefined")}}class s{constructor(){this.getAddressEnabled="1"===rbFrontendData?.getAddressEnabled||!0===rbFrontendData?.getAddressEnabled,this.customerTypeUpdater=new t,this.getAddressWidget=void 0}initialize(){if(!this.getAddressEnabled)return this.setupCustomerTypeOnInit(),void resursConsoleLog("Legacy Address Fetcher is disabled.");resursConsoleLog("Legacy Address Fetcher Ready."),e(document).ready((()=>{if("function"==typeof Resursbank_GetAddress&&document.getElementById("rb-ga-widget"))try{this.getAddressEnabled&&(this.getAddressWidget=new Resursbank_GetAddress({updateAddress:e=>{this.handleFetchAddressResponse(e),this.customerTypeUpdater.updateCustomerType(this.getAddressWidget.getCustomerType())}}),this.getAddressWidget.setupEventListeners()),this.setupCustomerTypeOnInit()}catch(e){console.error("Error initializing address widget:",e)}}))}isCorporate(){const t=e("#billing_company");return t.length>0&&""!==t.val().trim()}setupCustomerTypeOnInit(){(()=>{const e=this.isCorporate()?"LEGAL":"NATURAL";this.customerTypeUpdater.updateCustomerType(e)})(),e("#billing_company").on("input change",this.customerTypeUpdater.updateCustomerType)}handleFetchAddressResponse=(()=>{const e=e=>{let t="";switch(e.split("billing_")[1]||e.split("shipping_")[1]){case"first_name":t="firstName";break;case"last_name":t="lastName";break;case"country":t="countryCode";break;case"address_1":t="addressRow1";break;case"address_2":t="addressRow2";break;case"postcode":t="postalCode";break;case"city":t="postalArea";break;case"company":t="fullName";break;default:t=""}return t},t=t=>t.map((t=>({name:e(t.name),el:t}))).filter((e=>""!==e.name)),s=(e,t)=>{e.forEach((({name:e,el:s})=>{var r;const a=null!==(r=t[e])&&void 0!==r?r:s.value;"fullName"===e&&"LEGAL"!==this.getAddressWidget.getCustomerType()?s.value="":s.value=a;const n=s.closest(".woocommerce-invalid");n&&n.classList.remove("woocommerce-invalid","woocommerce-invalid-required-field")}))};return e=>{try{const r=(e=>{if(!e)return null;const s=Array.from(e.elements).filter((e=>e.name));return{billing:t(s.filter((e=>e.name.startsWith("billing_")))),shipping:t(s.filter((e=>e.name.startsWith("shipping_"))))}})((()=>{const e=document.forms.checkout;return e instanceof HTMLFormElement?e:null})());r&&s(r.billing,e)}catch(e){console.error("Error updating address fields:",e)}}})()}const r=window.wp.data,a=window.wc.wcBlocksData,n=window.wc.wcSettings;class o{widget=null;allPaymentMethods=[];constructor(e){this.customerTypeUpdater=new t,e?this.widget=new Resursbank_GetAddress({updateAddress:e=>{this.resetCartData();let t=this.getCartData();const s={first_name:"firstName",last_name:"lastName",address_1:"addressRow1",address_2:"addressRow2",postcode:"postalCode",city:"postalArea",country:"countryCode",company:"fullName"};for(const[r,a]of Object.entries(s)){if(!e.hasOwnProperty(a))throw new Error(`Missing required field "${a}" in data object.`);if("company"===r){this.setBillingAndShipping(t,"string"==typeof e[a]&&"LEGAL"===this.widget.getCustomerType()?e[a]:"");continue}const s="string"==typeof e[a]?e[a]:"";t.shippingAddress[r]=s,t.billingAddress[r]=s}(0,r.dispatch)(a.CART_STORE_KEY).setCartData(t),this.refreshPaymentMethods()}}):(this.loadAllPaymentMethods(),this.refreshPaymentMethods(),this.addCartUpdateListener("#shipping-company"),this.addCartUpdateListener("#billing-company"))}addCartUpdateListener(e){const t=new MutationObserver((()=>{const s=document.querySelector(e);s&&(t.disconnect(),s.addEventListener("change",(t=>{resursConsoleLog(`${e} has changed`,"DEBUG"),this.refreshPaymentMethods()})))}));t.observe(document.body,{childList:!0,subtree:!0})}setBillingAndShipping(e,t){e.shippingAddress.company=t,e.billingAddress.company=t}initialize(e){(0,r.select)(a.CART_STORE_KEY).hasFinishedResolution("getCartData")||(resursConsoleLog("Cart data not ready, triggered dispatch.","DEBUG"),(0,r.dispatch)(a.CART_STORE_KEY).invalidateResolution("getCartData")),e&&this.widget.setupEventListeners(),this.loadAllPaymentMethods(),this.refreshPaymentMethods()}loadAllPaymentMethods(){const e=(0,r.select)(a.CART_STORE_KEY).getCartData(),t=(0,n.getSetting)("resursbank_data",{}).payment_methods||[],s=new Set((e.paymentMethods||[]).map((e=>e.toLowerCase())));this.allPaymentMethods=[...e.paymentMethods||[]],t.forEach((e=>{const t=(e.id?.toLowerCase()||e.name?.toLowerCase()).trim();s.has(t)||this.allPaymentMethods.push(t)}))}getCartData(){const e=(0,r.select)(a.CART_STORE_KEY).getCartData();if(!e.shippingAddress||["first_name","last_name","address_1","address_2","postcode","city","country","company"].some((t=>void 0===e.shippingAddress[t])))throw new Error("Missing required shipping address data in cart.");return e}resetCartData(){let e=this.getCartData();e.shippingAddress.first_name="",e.shippingAddress.last_name="",e.shippingAddress.address_1="",e.shippingAddress.address_2="",e.shippingAddress.postcode="",e.shippingAddress.city="",e.shippingAddress.country="",e.shippingAddress.company="",(0,r.dispatch)(a.CART_STORE_KEY).setCartData(e)}refreshPaymentMethods(){if(!this.allPaymentMethods.length)return resursConsoleLog("No payment methods available for filtering.","DEBUG"),void this.loadAllPaymentMethods();const e=(0,r.select)(a.CART_STORE_KEY).getCartData();if(!e.paymentMethods)return console.warn("No payment methods found in cart data."),void(0,r.dispatch)(a.CART_STORE_KEY).invalidateResolution("getCartData");const t=(0,n.getSetting)("resursbank_data",{}).payment_methods||[],s=new Map(t.map((e=>[e.id?.toLowerCase()||e.name?.toLowerCase(),e]))),o="LEGAL"===this.widget?.getCustomerType()||""!==e.billingAddress?.company?.trim(),d=parseInt(e.totals.total_price,10)/Math.pow(10,e.totals.currency_minor_unit),i=this.allPaymentMethods.map((e=>{const t=e?.toLowerCase().trim(),r=s.get(t);if(r){const{enabled_for_legal_customer:t,enabled_for_natural_customer:s,min_purchase_limit:a,max_purchase_limit:n}=r;return(o&&t||!o&&s||!o&&t&&s)&&d>=a&&d<=n?e:null}return e})).filter(Boolean);(0,r.dispatch)(a.CART_STORE_KEY).setCartData({...e,paymentMethods:i}),this.customerTypeUpdater.updateCustomerType(o?"LEGAL":"NATURAL")}}document.addEventListener("DOMContentLoaded",(()=>{const e="1"===rbFrontendData?.getAddressEnabled||!0===rbFrontendData?.getAddressEnabled;document.querySelector(".woocommerce-checkout")&&"function"==typeof Resursbank_GetAddress&&(document.querySelector(".wc-block-components-form")?(resursConsoleLog("Address Fetcher found by element (Enabled: "+e+")."),new o(e).initialize(e)):new MutationObserver(((t,s)=>{document.querySelector(".wc-block-components-form")&&(new o(e).initialize(e),resursConsoleLog("Address Fetcher found by observer (Enabled: "+e+")."),s.disconnect())})).observe(document.body,{childList:!0,subtree:!0}),(new s).initialize())}))})();