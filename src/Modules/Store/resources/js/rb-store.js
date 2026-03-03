jQuery(document).ready(function () {
    const resursFetchStoresWidget = new Resursbank_FetchStores(
        {
            getUrl: function () {
                const returnUrl = typeof resursbank_store_admin_localize.url !== 'undefined' ?
                    resursbank_store_admin_localize.url : null;

                if (returnUrl === null) {
                    alert(resursbank_store_admin_localize.no_fetch_url);
                    return;
                }

                return returnUrl;
            },
            getPostData: function () {
                const data = Resursbank_FetchStores.prototype.getPostData.call(this);
                data.nonce = resursbank_store_admin_localize.nonce || '';
                return data;
            },
            handleFetchData: function (data) {
                Resursbank_FetchStores.prototype.handleFetchData.call(this, data);
            }
        }
    );

    const storeSelector = document.getElementById('resursbank_store_id');

    if (storeSelector !== null) {
        var storeFetchButton = document.createElement('button');
        storeFetchButton.textContent = resursbank_store_admin_localize.fetch_stores_translation;
        storeFetchButton.type = 'button';
        storeFetchButton.classList.add('button', 'button-primary');
        storeFetchButton.style.marginLeft = '10px';

        storeFetchButton.addEventListener('click', function () {
            resursFetchStoresWidget.fetchStores();
        });

        storeSelector.parentNode.insertBefore(storeFetchButton, storeSelector.nextSibling);
    }
});
