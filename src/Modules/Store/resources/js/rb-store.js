jQuery(document).ready(function () {
    const resursFetchStoresWidget = new Resursbank_FetchStores(
        {
            getUrl: function () {
                const returnUrl = typeof rbStoreAdminLocalize.url !== 'undefined' ?
                    rbStoreAdminLocalize.url : null;

                if (returnUrl === null) {
                    alert(rbStoreAdminLocalize.no_fetch_url);
                    return;
                }

                return returnUrl;
            },
            handleFetchData: function (data) {
                Resursbank_FetchStores.prototype.handleFetchData.call(this, data);

                // Make sure the save button remains disabled on errors.
                if (typeof data !== 'undefined' && !data.error) {
                    jQuery('.woocommerce-save-button').show();
                    jQuery('.woocommerce-save-button').removeAttr('disabled');
                } else {
                    jQuery('.woocommerce-save-button').hide();
                    jQuery('.woocommerce-save-button').attr('disabled', 'disabled');
                }
            }
        }
    );

    const storeSelector = document.getElementById('resursbank_store_id');

    if (storeSelector !== null) {
        jQuery('.woocommerce-save-button').hide();
        jQuery('.resursbank_environment').on('change', function () {
            jQuery('.woocommerce-save-button').hide();
        });

        var storeFetchButton = document.createElement('button');
        storeFetchButton.textContent = rbStoreAdminLocalize.fetch_stores_translation;
        storeFetchButton.type = 'button';
        storeFetchButton.classList.add('button', 'button-primary');
        storeFetchButton.style.marginLeft = '10px';

        storeFetchButton.addEventListener('click', function () {
            resursFetchStoresWidget.fetchStores();
        });

        storeSelector.addEventListener('change', function () {
            jQuery('.woocommerce-save-button').show();
            jQuery('.woocommerce-save-button').removeAttr('disabled');
        })

        storeSelector.parentNode.insertBefore(storeFetchButton, storeSelector.nextSibling);
    }
});
