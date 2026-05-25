/**
 * WordPress dependencies
 */
import defaultConfig from '@wordpress/scripts/config/webpack.config.js'; // eslint-disable-line no-restricted-syntax

/**
 * External dependencies
 */
import path from 'path';

/**
 * Define your script entrypoints here.
 *
 * Each entrypoint will be built into its own enqueuable asset.
 *
 * Blocks are handled separately via the `--package-manifest` flag in @wordpress/scripts
 */
const scriptEntries = {
	admin: path.resolve( import.meta.dirname, 'src/admin/index.tsx' ),
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

export default scriptConfig;
