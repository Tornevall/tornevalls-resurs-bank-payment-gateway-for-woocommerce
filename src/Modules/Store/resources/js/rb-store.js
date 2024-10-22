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
            }
        }
    );

    var storeSelector = document.getElementById('resursbank_store_id');
    if (storeSelector !== null) {
        var storeFetchButton = document.createElement('button');
        storeFetchButton.textContent = rbStoreAdminLocalize.fetch_stores_translation;
        storeFetchButton.type = 'button';
        storeFetchButton.classList.add('button', 'button-primary');
        storeFetchButton.style.marginLeft = '10px';

        storeFetchButton.addEventListener('click', function() {
            resursFetchStoresWidget.fetchStores();
        });

        storeSelector.parentNode.insertBefore(storeFetchButton, storeSelector.nextSibling);
    }
});
