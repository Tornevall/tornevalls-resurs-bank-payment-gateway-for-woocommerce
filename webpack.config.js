const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const WooCommerceDependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );
const path = require( 'path' );

/**
 * The content from here until the export statement is used to map dependencies
 * attached on window. in the browser to the external dependencies in the
 * webpack build. This is necessary to ensure webpack understands that some
 * dependencies will be available in the environment where the code is executed,
 * even if the resources are not available at build time.
 *
 * WooCommerce uses a pre-built monolith that bundles all relevant
 * functionality. We cannot/are not supposed to bundle these node modules in our
 * own module.
 */
const wcDepMap = {
	'@woocommerce/blocks-registry': [ 'wc', 'wcBlocksRegistry' ],
	'@woocommerce/settings': [ 'wc', 'wcSettings' ],
	'@woocommerce/block-data': [ 'wc', 'wcBlocksData' ],
};

const wcHandleMap = {
	'@woocommerce/blocks-registry': 'wc-blocks-registry',
	'@woocommerce/settings': 'wc-settings',
	'@woocommerce/blocks-data': 'wc-blocks-data',
};

const requestToExternal = ( request ) => {
	if ( wcDepMap[ request ] ) {
		return wcDepMap[ request ];
	}
};

const requestToHandle = ( request ) => {
	if ( wcHandleMap[ request ] ) {
		return wcHandleMap[ request ];
	}
};

// Export configuration.
module.exports = {
	...defaultConfig,
	entry: {
		'dist/gateway': path.resolve(
			__dirname,
			'src/Modules/Gateway/resources/ts/gateway.tsx'
		),
		'dist/update-address': path.resolve(
			__dirname,
			'src/Modules/GetAddress/resources/ts/update-address.ts'
		),
	},
	output: {
		path: path.resolve( __dirname, 'assets/js' ),
		filename: '[name].js',
	},
	plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) =>
				plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new WooCommerceDependencyExtractionWebpackPlugin( {
			requestToExternal,
			requestToHandle,
		} ),
	],
};
