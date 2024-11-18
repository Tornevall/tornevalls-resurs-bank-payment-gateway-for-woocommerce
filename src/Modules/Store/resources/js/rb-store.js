jQuery( document ).ready( function () {
	const resursFetchStoresWidget = new Resursbank_FetchStores( {
		getInputElements: function () {
			return [
				this.getSelectEnvironmentElement(),
				this.getClientIdElement(),
				this.getClientSecretElement(),
			];
		},
		getUrl: function () {
			const returnUrl =
				typeof rbStoreAdminLocalize.url !== 'undefined'
					? rbStoreAdminLocalize.url
					: null;

			if ( returnUrl === null ) {
				alert( 'Can not find fetch-url for stores.' );
				return;
			}

			return returnUrl;
		},
	} );
	resursFetchStoresWidget.setupEventListeners();
} );
