(()=>{"use strict";var e={20:(e,t,r)=>{var o=r(609),n=Symbol.for("react.element"),s=(Symbol.for("react.fragment"),Object.prototype.hasOwnProperty),a=o.__SECRET_INTERNALS_DO_NOT_USE_OR_YOU_WILL_BE_FIRED.ReactCurrentOwner,c={key:!0,ref:!0,__self:!0,__source:!0};function i(e,t,r){var o,i={},l=null,d=null;for(o in void 0!==r&&(l=""+r),void 0!==t.key&&(l=""+t.key),void 0!==t.ref&&(d=t.ref),t)s.call(t,o)&&!c.hasOwnProperty(o)&&(i[o]=t[o]);if(e&&e.defaultProps)for(o in t=e.defaultProps)void 0===i[o]&&(i[o]=t[o]);return{$$typeof:n,type:e,key:l,ref:d,props:i,_owner:a.current}}t.jsx=i,t.jsxs=i},848:(e,t,r)=>{e.exports=r(20)},609:e=>{e.exports=window.React}},t={};const r=window.wp.i18n,o=window.wc.wcBlocksRegistry,n=window.wc.wcSettings;var s=function r(o){var n=t[o];if(void 0!==n)return n.exports;var s=t[o]={exports:{}};return e[o](s,s.exports,r),s.exports}(848);const a=(0,n.getSetting)("resursbank2_data",{});a.forEach((e=>{(0,r.__)("Resursbank","woo-gutenberg-products-block");const t=e.title,n=()=>{const t=document.createElement("div"),r=document.createElement("style");return r.textContent=e.read_more_css,t.innerHTML=e.description,t.appendChild(r),(0,s.jsx)("div",{dangerouslySetInnerHTML:{__html:t.innerHTML}})},c=r=>{const{PaymentMethodLabel:o}=r.components;return(0,s.jsxs)("div",{className:"rb-payment-method-title",children:[(0,s.jsx)(o,{text:t}),(0,s.jsx)("div",{className:`rb-payment-method-logo rb-logo-type-${e.logo_type}`,dangerouslySetInnerHTML:{__html:e.logo}})]})},i={name:e.name,label:(0,s.jsx)(c,{}),content:(0,s.jsx)(n,{}),edit:(0,s.jsx)(n,{}),canMakePayment:()=>!0,ariaLabel:t,supports:{features:a.supports}};(0,o.registerPaymentMethod)(i)}))})();