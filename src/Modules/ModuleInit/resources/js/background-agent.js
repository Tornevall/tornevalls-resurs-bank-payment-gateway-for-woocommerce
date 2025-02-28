jQuery(document).ready(function ($) {
    function extractSubtotalFromData(data) {
        if (data && data["div.widget_shopping_cart_content"]) {
            let cartHtml = $('<div>').html(data["div.widget_shopping_cart_content"]); // Konvertera till jQuery-objekt
            let cartSubtotalText = cartHtml.find('.woocommerce-mini-cart__total .woocommerce-Price-amount').text().replace(/[^0-9.,]/g, '');

            if (cartSubtotalText.includes(',') && cartSubtotalText.includes('.')) {
                cartSubtotalText = cartSubtotalText.replace(',', '');
            } else {
                cartSubtotalText = cartSubtotalText.replace(',', '.'); // Ersätt komma med punkt om det bara finns ett
            }
            return parseFloat(cartSubtotalText) || 0;
        }
        return 0;
    }

    function extractSubtotalFromWCBlocks(data) {
        if (data && data.responses && data.responses.length > 0 && data.responses[0].body && data.responses[0].body.totals) {
            let subtotalText = data.responses[0].body.totals.total_price;
            return (parseFloat(subtotalText) / 100) || 0; // Divide by 100 to adjust for decimal placement
        }
        return 0;
    }

    // Function to execute rbBackgroundAgent request
    function runBackgroundAgent(cartSubtotal) {
        if (typeof cartSubtotal === 'undefined') {
            cartSubtotal = rbBackgroundAgent.cartTotals;
        }

        if (rbBackgroundAgent.can_request && cartSubtotal > 0) {
            jQuery.ajax({
                url: rbBackgroundAgent.url + '&c=' + encodeURIComponent(cartSubtotal),
            }).done((response) => {
                // Do nothing on success
            }).fail((error) => {
                // Do nothing on failure
            });
        }
    }

    $(document.body).on('added_to_cart removed_from_cart updated_wc_div', function (event, data) {
        let cartSubtotal = extractSubtotalFromData(data);
        runBackgroundAgent(cartSubtotal);
    });

    // Intercept WooCommerce AJAX refreshed fragments
    $(document).ajaxComplete(function (event, xhr, settings) {
        if (settings.url.includes("wc-ajax=get_refreshed_fragments") || settings.url.includes('/wc/store')) {
            try {
                let response = JSON.parse(xhr.responseText);
                if (response && response.fragments) {
                    let cartSubtotal = extractSubtotalFromData(response.fragments);
                    runBackgroundAgent(cartSubtotal);
                }
            } catch (error) {
                console.error("❌Resurs Error parsing WooCommerce refreshed fragments response:", error);
            }
        }
    });

    (function () {
        const originalFetch = window.fetch;
        window.fetch = function (...args) {
            return originalFetch.apply(this, args).then(response => {
                if (args[0].includes('/wc/store')) {
                    response.clone().json().then(data => {
                        let cartSubtotal = extractSubtotalFromWCBlocks(data);
                        runBackgroundAgent(cartSubtotal);
                    }).catch(error => console.error("❌Resurs Error parsing WC Blocks response:", error));
                }
                return response;
            });
        };
    })();

    if (rbBackgroundAgent.can_request) {
        // Execute the script immediately on page load, if allowed.
        runBackgroundAgent();
    }
});
