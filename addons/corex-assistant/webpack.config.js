/**
 * The `@wordpress/scripts` build, with one change (spec 129): a stylesheet that a script loads later
 * (the chat's CSS, imported by chat.js) is requested with its content hash, as its script already is.
 * Without it the address never changes, and a browser keeps an old copy after an update.
 */
// The root's devDependencies provide it to every workspace, as they do the build itself.
// eslint-disable-next-line import/no-extraneous-dependencies
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

function versionLazyStyles( config ) {
	for ( const plugin of config.plugins || [] ) {
		if ( plugin?.constructor?.name === 'MiniCssExtractPlugin' ) {
			plugin.options.chunkFilename = '[name].css?ver=[contenthash]';
		}
	}
	return config;
}

module.exports = Array.isArray( defaultConfig )
	? defaultConfig.map( versionLazyStyles )
	: versionLazyStyles( defaultConfig );
