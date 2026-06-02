/**
 * WordPress dependencies
 */
import defaultConfig from '@wordpress/scripts/config/webpack.config.js'; // eslint-disable-line no-restricted-syntax

/**
 * External dependencies
 */
import path from 'path';

const isDev = process.env.NODE_ENV === 'development';

/**
 * Define your script entrypoints here.
 *
 * Each entrypoint will be built into its own enqueuable asset.
 *
 * Blocks are handled separately via the `--package-manifest` flag in @wordpress/scripts
 */
const scriptEntries = {
	admin: path.resolve( import.meta.dirname, 'src/admin/guide/index.tsx' ),
	'admin-connections': path.resolve(
		import.meta.dirname,
		'src/admin/connections/index.tsx'
	),
	'admin-menu-icon': path.resolve(
		import.meta.dirname,
		'src/admin/styles/menu-icon.scss'
	),
};

/**
 * RtlCssPlugin added by Webpack to convert left, margin-left, etc. physical
 * properties to rtl like right, margin-right, etc.
 *
 * But we can use inset-inline-start, margin-inline-start, etc. logical
 * properties to achieve the same result.
 *
 * Hence, removing it from the plugins array.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/CSS/CSS_logical_properties_and_values
 */
const plugins = defaultConfig.plugins.filter(
	( plugin ) => plugin.constructor.name !== 'RtlCssPlugin'
);

const scriptConfig = {
	...defaultConfig,

	entry: {
		// WordPress stores them in a function.
		...defaultConfig.entry,
		...scriptEntries,
	},

	resolve: {
		...defaultConfig.resolve,
		extensions: [ '.tsx', '.ts', '.jsx', '.js' ],
		alias: {
			...( defaultConfig.resolve?.alias || {} ),
			'@': path.resolve( import.meta.dirname, 'src' ),
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

	plugins,
};

/**
 * In Hot Module Replacement (HMR) mode, we need to allow the dev server to proxy requests to the WordPress site.
 *
 * The SITE_URL environment variable should be set to the URL of the WordPress site during development.
 *
 * For example: http://localhost:8888
 *
 * @see https://webpack.js.org/configuration/dev-server/#devserverproxy
 */
if ( isDev && process.env.SITE_URL ) {
	const SITE_URL = process.env.SITE_URL;
	const SITE_HOSTNAME = new URL( SITE_URL ).hostname;

	scriptConfig.devServer = {
		...defaultConfig.devServer,
		allowedHosts: SITE_HOSTNAME,
		proxy: [
			{
				context: [ '/build' ],
				target: SITE_URL,
				pathRewrite: { '^/build': '' },
			},
		],
	};
}

export default scriptConfig;
