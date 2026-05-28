/**
 * External dependencies
 */
import path from 'path';
import HtmlWebpackPlugin from 'html-webpack-plugin'; // eslint-disable-line import/no-extraneous-dependencies

/**
 * WordPress dependencies
 */
import defaultConfig from '@wordpress/scripts/config/webpack.config.js'; // eslint-disable-line no-restricted-syntax,import/no-extraneous-dependencies

const isDev = process.env.NODE_ENV === 'development';

/**
 * Add one entry per MCP app. The app name must match the directory name under
 * src/apps/ and will become the output directory name under build-apps/.
 */
const mcpApps = {
	'pattern-approval': 'src/apps/pattern-approval/index.tsx',
};

const mcpAppEntries = Object.fromEntries(
	Object.entries( mcpApps ).map( ( [ name, file ] ) => [
		name,
		path.resolve( import.meta.dirname, file ),
	] )
);

const plugins = defaultConfig.plugins.filter(
	( plugin ) => plugin.constructor.name !== 'RtlCssPlugin'
);

const appsConfig = {
	...defaultConfig,
	mode: isDev ? 'development' : 'production',
	devtool: isDev ? 'inline-source-map' : false,

	entry: mcpAppEntries,

	output: {
		path: path.resolve( import.meta.dirname, 'build-apps' ),
		filename: '[name].js',
		publicPath: '%%PLUGIN_URL%%',
		clean: true,
		chunkFormat: false,
	},

	resolve: {
		extensions: [ '.ts', '.tsx', '.js', '.jsx' ],
		alias: {
			...defaultConfig.resolve.alias,
			'@apps': path.resolve( import.meta.dirname, 'src/apps' ),
		},
	},

	optimization: {
		...defaultConfig.optimization,
		runtimeChunk: false,
	},

	module: {
		...defaultConfig.module,
		rules: [
			...defaultConfig.module.rules,
			/**
			 * postcss-loader is already added in the defaultConfig
			 * but we need it here for tailwindcss.
			 */
			{
				test: /\.scss$/i,
				include: path.resolve( import.meta.dirname, 'src' ),
				use: [ 'postcss-loader' ],
			},
		],
	},

	plugins: [
		...plugins,
		new HtmlWebpackPlugin( {
			template: path.resolve(
				import.meta.dirname,
				'src/apps/template.html'
			),
			filename: '[name].html',
			scriptLoading: 'blocking',
		} ),
	],
};

/**
 * In Hot Module Replacement (HMR) mode, we need to allow the dev server to proxy requests to the WordPress site.
 *
 * The SITE_URL environment variable should be set to the URL of the WordPress site during development.
 *
 * @see https://webpack.js.org/configuration/dev-server/#devserverproxy
 */
if ( isDev && process.env.SITE_URL ) {
	const SITE_URL = process.env.SITE_URL;
	const SITE_HOSTNAME = new URL( SITE_URL ).hostname;

	appsConfig.devServer = {
		devMiddleware: {
			writeToDisk: true,
		},
		host: 'localhost',
		port: 8889,
		allowedHosts: SITE_HOSTNAME,
		proxy: [
			{
				context: [ '/build-apps' ],
				target: SITE_URL,
				pathRewrite: { '^/build-apps': '' },
			},
		],
	};

	appsConfig.output.publicPath = 'http://localhost:8889/build-apps/';
}

export default appsConfig;
