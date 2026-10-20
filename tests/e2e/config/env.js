/**
 * Single source of truth for E2E settings.
 */
/**
 * External dependencies
 */
import fs from 'node:fs';
import { createRequire } from 'node:module';
import path from 'node:path';
import { test } from '@playwright/test';

const root = process.cwd();
const definitions = JSON.parse(
	fs.readFileSync( path.join( root, 'tests/e2e/config/sites.json' ), 'utf8' )
);

export const projects = definitions.projects;
export const artifacts = path.join( root, 'artifacts' );

const wpCore = process.env.WP_ENV_CORE || '';

/**
 * @param {string|undefined} value Raw value.
 * @return {''|'plugin-update'|'wp-update'} The update type, or '' for all tests.
 */
function updateType( value ) {
	if ( ! value || value === 'all' ) {
		return '';
	}
	if ( value !== 'plugin-update' && value !== 'wp-update' ) {
		throw new Error(
			`Unknown E2E_UPDATE "${ value }". Use plugin-update, wp-update or all.`
		);
	}
	return value;
}

export const settings = {
	theme: process.env.E2E_THEME || 'twentytwentyfive',
	update: updateType( process.env.E2E_UPDATE ),
	extraTags: ( process.env.E2E_TAGS || '' )
		.split( /[\s,|]+/ )
		.filter( Boolean ),
	zip:
		process.env.PUBLISHIO_ZIP ||
		path.join( root, 'artifacts/zips/publishio.zip' ),
	ci: !! process.env.CI,
	slowMo: Number( process.env.E2E_SLOWMO || 0 ),
};

/**
 * Site definition with env overrides applied.
 *
 * @param {string} name Site name from sites.json.
 */
export function site( name ) {
	const def = definitions.sites[ name ];
	if ( ! def ) {
		throw new Error(
			`Unknown site "${ name }". Use one of: ${ Object.keys(
				definitions.sites
			).join( ', ' ) }.`
		);
	}
	return {
		...def,
		name,
		baseURL: process.env.WP_BASE_URL || def.baseURL,
		wpLabel: def.wp || ( wpCore ? wpCore.replace( /.*#/, '' ) : 'latest' ),
		phpLabel: def.php || process.env.WP_ENV_PHP_VERSION || 'wp-env default',
		wpCli:
			process.env.WP_CLI_CMD ||
			`npx wp-env --config=${ def.wpEnvConfig } run cli wp`,
		debugLogPath: path.join( root, def.debugLog ),
		storageState: path.join( artifacts, `storage-states/${ name }.json` ),
	};
}

/** Site of the current test's project. */
export function currentSite() {
	return site( test.info().project.metadata.site );
}

/**
 * The utils read WP_BASE_URL once at load; patch it per worker so each site gets its own.
 *
 * @param {string} baseURL The site's URL.
 */
export function pointWordPressUtilsAt( baseURL ) {
	const require = createRequire( __filename );
	const config = require(
		path.join(
			path.dirname(
				require.resolve( '@wordpress/e2e-test-utils-playwright' )
			),
			'config.js'
		)
	);
	config.WP_BASE_URL = baseURL;
}
