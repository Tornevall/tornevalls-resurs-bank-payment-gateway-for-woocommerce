/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

let variationDisplayPrice = 0;

jQuery(document).ready(function () {
    RB_PP_WIDGET_INSTANCE = Resursbank_PartPayment.createInstance(
        document.getElementById('rb-pp-widget-container'),
        {
            getAmount: function () {
                return variationDisplayPrice;
            },
            getQty: function () {
                var qty = jQuery('input.qty').val();
                return qty;
            },
            getObservableElements: function() {
                return [];
            },
            toggleLoader: function() {

            }
        }
    );
    jQuery('.variations_form').each(function () {
        jQuery(this).on('found_variation', function (event, variation) {
            variationDisplayPrice = variation.display_price;
            RB_PP_WIDGET_INSTANCE.updateStartingCost();
        });
    });
});
