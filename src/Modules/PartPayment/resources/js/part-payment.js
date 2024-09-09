/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

var variationDisplayPrice = 0;

jQuery(document).ready(function () {
    const qtyElement = document.querySelector('input.qty');

    // noinspection JSUndeclaredVariable (Ecom owned)
    RB_PP_WIDGET_INSTANCE = Resursbank_PartPayment.createInstance(
        document.getElementById('rb-pp-widget-container'),
        {
            getAmount: function () {
                // noinspection JSUnresolvedReference
                const price = (
                    typeof rbPpScript !== 'undefined' &&
                    typeof rbPpScript.product_price !== 'undefined' &&
                    variationDisplayPrice === 0
                ) ? rbPpScript.product_price
                    : variationDisplayPrice;

                return price * this.getQty();
            },
            getObservableElements: function () {
                return [qtyElement];
            },
            getQtyElement: function() {
                return qtyElement;
            }
        }
    );
    jQuery('.variations_form').each(function () {
        jQuery(this).on('found_variation', function (event, variation) {
            // noinspection JSUnresolvedReference (Woocommerce owned variables)
            variationDisplayPrice = variation.display_price;
            // noinspection JSIgnoredPromiseFromCall
            RB_PP_WIDGET_INSTANCE.updateStartingCost();
        });
    });
});
