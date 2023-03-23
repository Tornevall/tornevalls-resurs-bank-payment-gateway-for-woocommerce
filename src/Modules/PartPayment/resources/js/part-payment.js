/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

jQuery(document).ready(function () {
    jQuery('.variations_form').each(function () {
        jQuery(this).on('found_variation', function (event, variation) {
            let price = variation.display_price;
            getStartingAtCost(price);
        });
    });
});
