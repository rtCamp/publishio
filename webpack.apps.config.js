/**
 * External dependencies
 */
import path from 'path';
import HtmlWebpackPlugin from 'html-webpack-plugin'; // eslint-disable-line import/no-extraneous-dependencies
import MiniCssExtractPlugin from 'mini-css-extract-plugin'; // eslint-disable-line import/no-extraneous-dependencies

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

const appsConfig = {
	mode: isDev ? 'development' : 'production',
	devtool: isDev ? 'inline-source-map' : false,

	entry: mcpAppEntries,

	output: {
		path: path.resolve( import.meta.dirname, 'build-apps' ),
		filename: '[name]/script.js',
		publicPath: '%%PLUGIN_URL%%',
		clean: true,
	},

	resolve: {
		extensions: [ '.ts', '.tsx', '.js', '.jsx' ],
		alias: {
			'@apps': path.resolve( import.meta.dirname, 'src/apps' ),
		},
	},

	optimization: {
		runtimeChunk: false,
	},

	module: {
		rules: [
			{
				test: /\.[jt]sx?$/,
				exclude: /node_modules/,
				use: 'babel-loader',
			},
			{
				test: /\.css$/,
				use: [
					MiniCssExtractPlugin.loader,
					'css-loader',
					'postcss-loader',
				],
			},
		],
	},

	plugins: [
		new MiniCssExtractPlugin( { filename: '[name]/style.css' } ),
		new HtmlWebpackPlugin( {
			template: path.resolve(
				import.meta.dirname,
				'src/apps/template.html'
			),
			filename: '[name]/index.html',
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
}

export default appsConfig;
