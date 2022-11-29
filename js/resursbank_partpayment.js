jQuery(document).ready(function () {
    jQuery('.variations_form').each(function () {
        jQuery(this).on('found_variation', function (event, variation) {
            let price = variation.display_price;
            getStartingAtCost(price).then(resp => {
                jQuery('#rb-pp-error').hide();
                jQuery('#rb-pp-starting-at').html(resp.startingAt);
            }).catch(function() {
                jQuery('#rb-pp-error').show();
            });
        });
    });
});