jQuery(document).ready(function ($) {
    if (typeof Resursbank_GetPeriods !== 'undefined') {
        var dropDownPeriods = $('#resursbank_part_payment_period');
        dropDownPeriods.on('change', function () {
            if (dropDownPeriods.find('option').length > 1) {
                $('#resursbank_part_payment_period').prop('disabled', false);
            }

            $('#resursbank_part_payment_payment_method').on('change', function () {
                $('#resursbank_part_payment_period').prop('disabled', false);
            });
        })
    }
});
