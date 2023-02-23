document.addEventListener('DOMContentLoaded', function () {
    (function () {
        'use strict';

        const deleteSelectOptions = (element) => {
            for (let i = element.options.length; i >= 0; i--) {
                element.remove(i);
            }
        }

        const initPaymentMethodSelect = () => {
            let paymentMethodSelect = document.getElementById('resursbank_part_payment_payment_method');
            if (!jQuery.isEmptyObject(paymentMethodSelect)) {
                paymentMethodSelect.addEventListener(
                    'change',
                    async function (event) {
                        let periodSelect = document.getElementById('resursbank_part_payment_period');

                        periodSelect.disabled = true;
                        document.getElementById('resursbank_part_payment_period_spinner').style.display = 'inline';

                        let durations = await rbGetDurationsForPaymentMethodId(event.target.value);
                        if (!jQuery.isEmptyObject(periodSelect) && periodSelect.nodeName === 'SELECT') {
                            deleteSelectOptions(periodSelect);
                            if (durations.status === 'success') {
                                if (!durations.response.hasOwnProperty('error')) {
                                    for (let k in durations.response) {
                                        let opt = document.createElement('option');
                                        opt.value = k;
                                        opt.innerHTML = durations.response[k];
                                        periodSelect.appendChild(opt);
                                    }

                                    periodSelect.disabled = false;
                                }
                            }
                        }

                        document.getElementById('resursbank_part_payment_period_spinner').style.display = 'none';
                    }
                );
            }
        };

        initPaymentMethodSelect();
    }());
});