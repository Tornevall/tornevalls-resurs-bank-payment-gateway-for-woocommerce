/**
 * Copyright © Resurs Bank AB. All rights reserved.
 * See LICENSE for license details.
 */

var variationDisplayPrice = 0;
var rbPpMonthlyCost = 0;

/**
 * Get current price based on variation or single product prices.
 * @returns {*|number}
 */
function getRbPpPriceFromWooCom() {
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
function getRbPpMaxApplicationLimit() {
    return typeof rbPpScript !== 'undefined' &&
    typeof rbPpScript.maxApplicationLimit !== 'undefined' ? rbPpScript.maxApplicationLimit : 0;
}

/**
 * Min application limit.
 * @returns {*|number}
 */
function getRbPpMinApplicationLimit() {
    return typeof rbPpScript !== 'undefined' &&
    typeof rbPpScript.minApplicationLimit !== 'undefined' ? rbPpScript.minApplicationLimit : 0;
}

function getThreshold() {
    return typeof rbPpScript !== 'undefined' &&
    typeof rbPpScript.thresholdLimit !== 'undefined' ? rbPpScript.thresholdLimit : 0;
}

function getMonthlyCost() {
    if (rbPpMonthlyCost === 0) {
        rbPpMonthlyCost = typeof rbPpScript !== 'undefined' &&
        typeof rbPpScript.monthlyCost !== 'undefined' ? rbPpScript.monthlyCost : 0;
    }

    return rbPpMonthlyCost;
}

/**
 * Returns true if threshold are met.
 * @returns {boolean}
 */
function isAllowedThreshold() {
    return getMonthlyCost() >= getThreshold() &&
        getRbPpPriceFromWooCom() >= getRbPpMinApplicationLimit() &&
        getRbPpPriceFromWooCom() <= getRbPpMaxApplicationLimit();
}

function updateRbPpWidgetByThreshold() {
    var rbPpWidget = document.getElementById('rb-pp-widget-container');
    if (rbPpWidget !== null) {
        if (isAllowedThreshold()) {
            rbPpWidget.style.display = '';
        } else {
            rbPpWidget.style.display = 'none';
        }
    }
}

jQuery(document).ready(function () {
    const qtyElement = document.querySelector('input.qty');

    // Allow us to show and hide our part payment widget, based on allowed threshold.
    qtyElement.addEventListener('change', function () {
        updateRbPpWidgetByThreshold();
    });

    updateRbPpWidgetByThreshold();

    // noinspection JSUndeclaredVariable (Ecom owned)
    RB_PP_WIDGET_INSTANCE = Resursbank_PartPayment.createInstance(
        document.getElementById('rb-pp-widget-container'),
        {
            getAmount: function () {
                // noinspection JSUnresolvedReference
                return getRbPpPriceFromWooCom() * this.getQty();
            },
            getObservableElements: function () {
                return [qtyElement];
            },
            getQtyElement: function () {
                return qtyElement;
            }
        }
    );

    // Intercept the original method for the widget response and fetch new monthly cost, each call, for use
    // with thresholds.
    const originalHandleFetchResponse = RB_PP_WIDGET_INSTANCE.handleFetchResponse;
    RB_PP_WIDGET_INSTANCE.handleFetchResponse = function (response) {
        return originalHandleFetchResponse.call(this, response).then((data) => {
            if (data.monthlyCost !== 'undefined') {
                rbPpMonthlyCost = data.monthlyCost;
                updateRbPpWidgetByThreshold();
            }
            return data;
        }).catch((error) => {
            throw error;
        });
    }

    jQuery('.variations_form').each(function () {
        jQuery(this).on('found_variation', function (event, variation) {
            // noinspection JSUnresolvedReference (Woocommerce owned variables)
            variationDisplayPrice = variation.display_price;
            // noinspection JSIgnoredPromiseFromCall
            RB_PP_WIDGET_INSTANCE.updateStartingCost();
        });
    });
});
