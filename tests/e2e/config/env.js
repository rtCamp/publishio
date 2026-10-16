/**
 * Single source of truth for E2E settings.
 *
 * Sites (one WordPress each) and the Playwright projects that run on them are defined once in
 * sites.json. Pick what to run with --project; only the setup of the selected projects' sites
 * runs. Everything that changes per run comes from environment variables, so
 * playwright.config.js and the workflows never need editing.
 *
 *   WP_ENV_CORE         wp-env core for sites without a pinned version, e.g. WordPress/WordPress#7.0
 *   WP_ENV_PHP_VERSION  PHP for sites without a pinned version, e.g. 8.3
 *   E2E_THEME           theme slug                                 (default: twentytwentyfive)
 *   E2E_TAGS            extra tags to run, e.g. "@oauth @patterns" (set by impact analysis)
 *   E2E_UPDATE          plugin-update | wp-update: run only the tests tagged for that kind of change
 *                       (default: every test of the selected projects)
 *   PUBLISHIO_ZIP       zip under test for "zip" sites            (default: artifacts/zips/publishio.zip)
 *   WP_BASE_URL         run against another site, e.g. staging    (default: the site's wp-env URL)
 *   WP_CLI_CMD          WP-CLI command for that site               (default: the site's wp-env CLI)
 *   E2E_SLOWMO          ms to pause after each browser action, for watching --headed runs (default: 0)
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
 * Validate E2E_UPDATE.
 *
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
 * Everything about one site, with env overrides applied.
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
		// Pinned sites ignore WP_ENV_CORE / WP_ENV_PHP_VERSION (bin/e2e.mjs drops them on start).
		wpLabel: def.wp || ( wpCore ? wpCore.replace( /.*#/, '' ) : 'latest' ),
		phpLabel: def.php || process.env.WP_ENV_PHP_VERSION || 'wp-env default',
		wpCli:
			process.env.WP_CLI_CMD ||
			`npx wp-env --config=${ def.wpEnvConfig } run cli wp`,
		debugLogPath: path.join( root, def.debugLog ),
		storageState: path.join( artifacts, `storage-states/${ name }.json` ),
	};
}

/** The site of the project the current test (or hook) belongs to. */
export function currentSite() {
	return site( test.info().project.metadata.site );
}

/**
 * Point @wordpress/e2e-test-utils-playwright at a site. It reads WP_BASE_URL from the environment
 * once, when it loads, and uses it to discover the REST API root; with several sites in one run
 * every REST call would otherwise go to http://localhost:8889. Its config module exports a plain
 * property that it reads on each call, so setting it once per worker (one project) is enough.
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
