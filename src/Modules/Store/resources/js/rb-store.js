jQuery(document).ready(function () {
    const resursFetchStoresWidget = new Resursbank_FetchStores(
        {
            getInputElements: function () {
                return [
                    this.getSelectEnvironmentElement(),
                    this.getClientIdElement(),
                    this.getClientSecretElement()
                ];
            },
            getUrl: function () {
                const returnUrl = typeof rbStoreAdminLocalize.url !== 'undefined' ?
                    rbStoreAdminLocalize.url : null;

                if (returnUrl === null) {
                    alert('Can not find fetch-url for stores.');
                    return;
                }

                return returnUrl;
            }
        }
    );
    resursFetchStoresWidget.setupEventListeners();
    var selectStoreElement = resursFetchStoresWidget.getSelectStoreElement();
    var selectClientSecretElement = resursFetchStoresWidget.getClientSecretElement();

    // In some rare cases when password helpers (like lastpass) updates passwords in text boxes
    // but the element don't change, a double click may help refreshing stores with the
    // correct password.
    selectStoreElement.addEventListener('dblclick', function () {
        resursFetchStoresWidget.fetchStores();
    });
    selectClientSecretElement.addEventListener('dblclick', function () {
        resursFetchStoresWidget.fetchStores();
    })
})
