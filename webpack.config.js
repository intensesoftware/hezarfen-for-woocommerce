/**
 * Custom webpack config that extends the default @wordpress/scripts config and
 * externalizes WooCommerce Blocks packages (e.g. @woocommerce/blocks-checkout,
 * @woocommerce/block-data, @woocommerce/settings) to the `wc.*` runtime globals,
 * adding the matching `wc-*` script dependencies to the generated asset file.
 *
 * Only the checkout block bundle needs this; the other (admin) bundles continue
 * to use the default @wordpress/scripts pipeline via their own CLI invocations.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const WooCommerceDependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );

module.exports = {
	...defaultConfig,
	plugins: [
		...defaultConfig.plugins.filter(
			( plugin ) =>
				plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
		),
		new WooCommerceDependencyExtractionWebpackPlugin(),
	],
};
