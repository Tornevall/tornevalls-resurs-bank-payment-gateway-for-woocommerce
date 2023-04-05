// document.addEventListener('DOMContentLoaded', function () {
//     (function () {
//         'use strict';
//
//         alert(2);
//     }());
// });

jQuery(document).ajaxSuccess((event, xhr, settings) => {
    const reloadActions = [
        'woocommerce_add_order_fee',
        'woocommerce_remove_order_coupon',
        'woocommerce_add_coupon_discount',
        'woocommerce_calc_line_taxes',
        'woocommerce_add_order_item'
    ];
    if (
        settings.hasOwnProperty('data') &&
        typeof settings.data === 'string'
    ) {
        const action = new URLSearchParams(settings.data).get('action');

        if (reloadActions.includes(action)) {
            window.location.reload();
        }
    }
});