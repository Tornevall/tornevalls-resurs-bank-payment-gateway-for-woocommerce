/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

jQuery(document).ready(function () {
    jQuery('.variations_form').each(function () {
        jQuery(this).on('found_variation', function (event, variation) {
            RB_PP_WIDGET_INSTANCE = Resursbank_PartPayment.createInstance(
                document.getElementById('rb-pp-widget-container'),
                {
                    getAmount: function() {
                        alert(variation.display_price);
                    }
                }
            );
        });
    });
});
