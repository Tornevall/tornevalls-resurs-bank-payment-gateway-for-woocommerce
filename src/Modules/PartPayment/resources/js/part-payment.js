/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

var variationDisplayPrice = 0;

/**
 * Get current price based on variation or single product prices.
 * @returns {*|number}
 */
function getRbPpPrice() {
    return (
        typeof rbPpScript !== 'undefined' &&
        typeof rbPpScript.product_price !== 'undefined' &&
        variationDisplayPrice === 0
    ) ? rbPpScript.product_price
        : variationDisplayPrice;
}

/**
 * Max application limit.
 * @returns {*|number}
 */
function getRbPpMax() {
    return typeof rbPpScript !== 'undefined' &&
    typeof rbPpScript.maxApplicationLimit !== 'undefined' ? rbPpScript.maxApplicationLimit : 0;
}

/**
 * Min application limit.
 * @returns {*|number}
 */
function getRbPpMin() {
    return typeof rbPpScript !== 'undefined' &&
    typeof rbPpScript.minApplicationLimit !== 'undefined' ? rbPpScript.minApplicationLimit : 0;
}

/**
 * Returns true if threshold are met.
 * @returns {boolean}
 */
function isAllowedThreshold() {
    return getRbPpPrice() >= getRbPpMin() && getRbPpPrice() <= getRbPpMax();
}

jQuery(document).ready(function () {
    const qtyElement = document.querySelector('input.qty');

    // Allow us to show and hide our part payment widget, based on allowed threshold.
    qtyElement.addEventListener('change', function () {
        var rbPpWidget = document.getElementById('rb-pp-widget-container');
        if (isAllowedThreshold()) {
            rbPpWidget.style.display = '';
        } else {
            rbPpWidget.style.display = 'none';
        }
    });

    // noinspection JSUndeclaredVariable (Ecom owned)
    RB_PP_WIDGET_INSTANCE = Resursbank_PartPayment.createInstance(
        document.getElementById('rb-pp-widget-container'),
        {
            getAmount: function () {
                // noinspection JSUnresolvedReference
                return getRbPpPrice() * this.getQty();
            },
            getObservableElements: function () {
                return [qtyElement];
            },
            getQtyElement: function () {
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
