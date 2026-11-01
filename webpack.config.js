const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		editor: path.resolve( __dirname, 'src/modal/index.js' ),
		admin: path.resolve( __dirname, 'src/admin/index.js' ),
		elementor: path.resolve( __dirname, 'src/elementor/index.js' ),
		wpbakery: path.resolve( __dirname, 'src/wpbakery/index.js' ),
		gutenberg: path.resolve( __dirname, 'src/gutenberg/index.js' ),
		media: path.resolve( __dirname, 'src/media/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
};
