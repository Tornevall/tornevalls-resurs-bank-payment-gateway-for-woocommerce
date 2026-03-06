jQuery(document).ready(function () {
    const resursFetchStoresWidget = new Resursbank_FetchStores(
        {
            getUrl: function () {
                const returnUrl = typeof resursbankabpaygwStoreAdminLocalize.url !== 'undefined' ?
                    resursbankabpaygwStoreAdminLocalize.url : null;

                if (returnUrl === null) {
                    alert(resursbankabpaygwStoreAdminLocalize.no_fetch_url);
                    return;
                }

                return returnUrl;
            },
            getPostData: function () {
                const data = Resursbank_FetchStores.prototype.getPostData.call(this);
                data.nonce = resursbankabpaygwStoreAdminLocalize.nonce || '';
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
        storeFetchButton.textContent = resursbankabpaygwStoreAdminLocalize.fetch_stores_translation;
        storeFetchButton.type = 'button';
        storeFetchButton.classList.add('button', 'button-primary');
        storeFetchButton.style.marginLeft = '10px';

        storeFetchButton.addEventListener('click', function () {
            resursFetchStoresWidget.fetchStores();
        });

        storeSelector.parentNode.insertBefore(storeFetchButton, storeSelector.nextSibling);
    }
});
