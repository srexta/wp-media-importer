/**
 * Default @wordpress/scripts config with one bundle per admin screen.
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		settings: path.resolve( __dirname, 'src/settings/index.js' ),
		importer: path.resolve( __dirname, 'src/importer/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
		filename: '[name].js',
	},
};
