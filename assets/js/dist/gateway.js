(()=>{"use strict";var e={20:(e,t,r)=>{var o=r(609),n=Symbol.for("react.element"),s=(Symbol.for("react.fragment"),Object.prototype.hasOwnProperty),a=o.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED.ReactCurrentOwner,i={key:!0,ref:!0,__self:!0,__source:!0};function l(e,t,r){var o,l={},c=null,d=null;for(o in void 0!==r&&(c=""+r),void 0!==t.key&&(c=""+t.key),void 0!==t.ref&&(d=t.ref),t)s.call(t,o)&&!i.hasOwnProperty(o)&&(l[o]=t[o]);if(e&&e.defaultProps)for(o in t=e.defaultProps)void 0===l[o]&&(l[o]=t[o]);return{$$typeof:n,type:e,key:c,ref:d,props:l,_owner:a.current}}t.jsx=l,t.jsxs=l},848:(e,t,r)=>{e.exports=r(20)},609:e=>{e.exports=window.React}},t={};function r(o){var n=t[o];if(void 0!==n)return n.exports;var s=t[o]={exports:{}};return e[o](s,s.exports,r),s.exports}r.n=e=>{var t=e&&e.__esModule?()=>e.default:()=>e;return r.d(t,{a:t}),t},r.d=(e,t)=>{for(var o in t)r.o(t,o)&&!r.o(e,o)&&Object.defineProperty(e,o,{enumerable:!0,get:t[o]})},r.o=(e,t)=>Object.prototype.hasOwnProperty.call(e,t);var o=r(609),n=r.n(o);const s=window.wp.data,a=window.wc.wcBlocksData,i=window.wc.wcBlocksRegistry,l=window.wc.wcSettings;var c=r(848);const d=(0,l.getSetting)("resursbank_data",{}),u=(e,t)=>""!==e.company||""!==t.company;Array.isArray(d.payment_methods)&&0!==d.payment_methods.length&&("function"==typeof l.getSetting?"function"==typeof i.registerPaymentMethod?"function"==typeof s.select?d.payment_methods.forEach((e=>{const t=e.title,r=()=>{const t=(0,s.select)(a.CART_STORE_KEY).getCartData(),r=(e=>parseInt(e.totals.total_price,10)/Math.pow(10,e.totals.currency_minor_unit))(t),o=t?.billing_address?.country||"",i=t?.shipping_address?.country||"";return n().useEffect((()=>{const e=document.querySelector("iframe.rb-rm-iframe");e&&((e,t)=>{let r=e.getAttribute("src");if(r){const o=r.lastIndexOf("=");-1!==o&&(r=r.substring(0,o+1)+t,e.setAttribute("src",r))}})(e,r)}),[r]),(0,c.jsxs)("div",{children:[(0,c.jsx)("div",{dangerouslySetInnerHTML:{__html:e.description}}),(0,c.jsx)("div",{dangerouslySetInnerHTML:{__html:e.costlist}}),("SE"===o||"SE"===i)&&(0,c.jsx)("div",{dangerouslySetInnerHTML:{__html:e.price_signage_warning}}),(0,c.jsx)("style",{children:e.read_more_css})]})},o=r=>{const{PaymentMethodLabel:o}=r.components;return(0,c.jsxs)("div",{className:"rb-payment-method-title",children:[(0,c.jsx)(o,{text:t}),(0,c.jsx)("div",{className:`rb-payment-method-logo rb-logo-type-${e.logo_type}`,dangerouslySetInnerHTML:{__html:e.logo}})]})};(0,i.registerPaymentMethod)({name:e.name,paymentMethodId:e.name,label:(0,c.jsx)(o,{}),content:(0,c.jsx)(r,{}),edit:(0,c.jsx)(r,{}),canMakePayment:t=>{if(t.billingAddress.country!==d.allowed_country)return resursConsoleLog("Country does not match.","DEBUG"),!1;if(!((e,t,r)=>!(u(e,t)&&!r.enabled_for_legal_customer||!u(e,t)&&!r.enabled_for_natural_customer)||(resursConsoleLog("Exclude "+r.title+": Customer type not matching.","DEBUG"),!1))(t.billingAddress,t.shippingAddress,e))return resursConsoleLog("Customer type does not match.","DEBUG"),!1;const r=parseInt(t.cartTotals.total_price,10)/Math.pow(10,t.cartTotals.currency_minor_unit);return!(r<e.min_purchase_limit||r>e.max_purchase_limit)||(resursConsoleLog(e.title+": Order total ("+r+") does not match with "+e.min_purchase_limit+" and "+e.max_purchase_limit+".","DEBUG"),!1)},ariaLabel:t,supports:{blockBasedCheckout:"resursbank"!==e.name,features:["products","shipping","coupons"]}})})):console.error("WooCommerce: select is not available."):console.error("WooCommerce Blocks: registerPaymentMethod is not available."):console.error("WooCommerce: getSetting is not available."))})();