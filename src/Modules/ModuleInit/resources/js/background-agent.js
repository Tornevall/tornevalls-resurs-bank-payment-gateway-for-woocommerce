jQuery(document).ready(function ($) {
    /**
     * Extract subtotal from html element based mini-cart widget generated from AJAX requests.
     * @param cartHtml
     * @returns {number|number}
     */
    function extractSubtotal(cartHtml) {
        let cartSubtotalText = cartHtml.find('.woocommerce-mini-cart__total .woocommerce-Price-amount')
            .text()
            .replace(/[^0-9.,]/g, '');

        if (cartSubtotalText.includes(',') && cartSubtotalText.includes('.')) {
            cartSubtotalText = cartSubtotalText.replace(',', '');
        } else {
            cartSubtotalText = cartSubtotalText.replace(',', '.');
        }
        return parseFloat(cartSubtotalText) || 0;
    }

    /**
     * Extract subtotal from shopping cart element (requested from ajax).
     * @param data
     * @returns {number}
     */
    function extractSubtotalFromData(data) {
        if (data?.["div.widget_shopping_cart_content"]) {
            return extractSubtotal($('<div>').html(data["div.widget_shopping_cart_content"]));
        }
        return 0;
    }

    /**
     * Extract subtotal data from WC blocks requests.
     *
     * @param data
     * @returns {number|number}
     */
    function extractSubtotalFromWCBlocks(data) {
        return data?.responses?.[0]?.body?.totals?.total_price ? parseFloat(data.responses[0].body.totals.total_price) / 100 : 0;
    }

    /**
     * Background Agent. Running cache renderers in background to not ruin customer experience.
     *
     * @param cartSubtotal
     */
    function runBackgroundAgent(cartSubtotal = rbBackgroundAgent.cartTotals) {
        if (rbBackgroundAgent.can_request && cartSubtotal > 0) {
            jQuery.ajax({url: `${rbBackgroundAgent.url}&c=${encodeURIComponent(cartSubtotal)}`});
        }
    }

    /**
     * WooCommerce Triggers (legacy).
     */
    $(document.body).on('added_to_cart removed_from_cart updated_wc_div', (event, data) => {
        runBackgroundAgent(extractSubtotalFromData(data));
    });

    /**
     * WooCommerce Fragment Fetcher (legacy).
     */
    $(document).ajaxComplete((event, xhr, settings) => {
        if (settings.url.includes("wc-ajax=get_refreshed_fragments") || settings.url.includes('/wc/store')) {
            try {
                let response = JSON.parse(xhr.responseText);
                if (response?.fragments) {
                    runBackgroundAgent(extractSubtotalFromData(response.fragments));
                }
            } catch (error) {
                console.error("❌Resurs Error parsing WooCommerce refreshed fragments response:", error);
            }
        }
    });

    /**
     * Blocks interceptor, for picking up interesting values like subtotal.
     */
    (function () {
        const originalFetch = window.fetch;
        window.fetch = function (...args) {
            return originalFetch.apply(this, args).then(response => {
                if (args[0].includes('/wc/store')) {
                    response.clone().json().then(data => {
                        runBackgroundAgent(extractSubtotalFromWCBlocks(data));
                    }).catch(error => console.error("❌Resurs Error parsing WC Blocks response:", error));
                }
                return response;
            });
        };
    })();

    /**
     * Decide if we can run background agent from page init or if we should be idle.
     * This is executed from the localization side, for where we look for a page that we are allowed
     * to execute the background agent from (or if the cart is empty, we don't have to do this either).
     */
    if (rbBackgroundAgent.can_request) {
        runBackgroundAgent();
    }
});
