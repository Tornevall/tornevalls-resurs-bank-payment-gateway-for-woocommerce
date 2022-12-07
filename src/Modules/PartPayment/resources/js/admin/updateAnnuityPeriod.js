function deleteSelectOptions(element) {
    for (let i = element.options.length; i >= 0; i--) {
        element.remove(i);
    }
}

jQuery(document).ready(function() {
    document.getElementById('resursbank_partpayment_paymentmethod').addEventListener(
        'change',
        async function(event) {
            let durations = await getDurationsForPaymentMethodId(event.target.value);
            deleteSelectOptions(document.getElementById('resursbank_partpayment_period'));
            if (durations.status === 'success') {
                for (let k in durations.response) {
                    let opt = document.createElement('option');
                    opt.value = k;
                    opt.innerHTML = durations.response[k];
                    document.getElementById('resursbank_partpayment_period').appendChild(opt);
                }
            }
        }
    );
});
