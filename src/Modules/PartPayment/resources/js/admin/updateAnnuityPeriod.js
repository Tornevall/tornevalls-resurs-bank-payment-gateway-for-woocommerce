document.addEventListener('DOMContentLoaded', function () {
    (function () {
        'use strict';

        const deleteSelectOptions = (element) => {
            for (let i = element.options.length; i >= 0; i--) {
                element.remove(i);
            }
        }

        const initPaymentMethodSelect = () => {
            let paymentMethodSelect = document.getElementById('resursbank_partpayment_paymentmethod');
            if (!jQuery.isEmptyObject(paymentMethodSelect)) {
                paymentMethodSelect.addEventListener(
                    'change',
                    async function (event) {
                        let durations = await rbGetDurationsForPaymentMethodId(event.target.value);
                        let periodSelect = document.getElementById('resursbank_partpayment_period');
                        if (!jQuery.isEmptyObject(periodSelect) && periodSelect.nodeName === 'SELECT') {
                            deleteSelectOptions(periodSelect);
                            if (durations.status === 'success') {
                                for (let k in durations.response) {
                                    let opt = document.createElement('option');
                                    opt.value = k;
                                    opt.innerHTML = durations.response[k];
                                    periodSelect.appendChild(opt);
                                }
                            }
                        }
                    }
                );
            }
        };

        initPaymentMethodSelect();
    }());
});