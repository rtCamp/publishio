/**
 * Start and stop a site's wp-env instance from the setup and teardown projects.
 *
 * A site that setup had to start is recorded in artifacts/sites/<site>.started; teardown stops
 * only those, so a site you started yourself (node bin/e2e.mjs <project> start) keeps running
 * between local runs. In CI nothing runs beforehand, so every site is started and stopped.
 *
 *   E2E_KEEP_SITES=1   never stop sites in teardown
 *   E2E_STOP_SITES=1   stop every used site in teardown, even ones setup did not start
 */
/**
 * External dependencies
 */
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
/**
 * Internal dependencies
 */
import { artifacts } from '../config/env';

/**
 * Environment for wp-env: a site that pins its WordPress/PHP version ignores the per-run overrides.
 *
 * @param {import('../config/env').Site} site Site.
 * @return {NodeJS.ProcessEnv} Environment for the wp-env process.
 */
function wpEnvEnvironment( site ) {
	const env = { ...process.env };
	if ( site.wp ) {
		delete env.WP_ENV_CORE;
	}
	if ( site.php ) {
		delete env.WP_ENV_PHP_VERSION;
	}
	return env;
}

/**
 * Run a wp-env command for the site, streaming its output to the run's log.
 *
 * @param {import('../config/env').Site} site    Site.
 * @param {string[]}                     args    wp-env arguments, e.g. [ 'start' ].
 * @param {number}                       timeout Milliseconds before the command is killed.
 */
function wpEnv( site, args, timeout ) {
	execFileSync(
		'npx',
		[ 'wp-env', `--config=${ site.wpEnvConfig }`, ...args ],
		{
			stdio: 'inherit',
			env: wpEnvEnvironment( site ),
			timeout,
			shell: process.platform === 'win32',
		}
	);
}

const marker = ( site ) =>
	path.join( artifacts, 'sites', `${ site.name }.started` );

/**
 * Whether the site answers HTTP requests.
 *
 * @param {import('../config/env').Site} site Site.
 * @return {Promise<boolean>} True when WordPress responds.
 */
export async function isRunning( site ) {
	try {
		const res = await fetch( site.baseURL, {
			redirect: 'manual',
			signal: AbortSignal.timeout( 5_000 ),
		} );
		return res.status < 500;
	} catch {
		return false;
	}
}

/**
 * Start the site's wp-env instance unless it is already running. Sites reached through
 * WP_BASE_URL (e.g. staging) are never started or stopped here.
 *
 * @param {import('../config/env').Site} site Site.
 * @return {Promise<boolean>} True when this call started the site.
 */
export async function ensureRunning( site ) {
	if ( process.env.WP_BASE_URL || ( await isRunning( site ) ) ) {
		return false;
	}
	// wp-env maps these into the container, so they must exist first.
	fs.mkdirSync( path.dirname( site.debugLogPath ), { recursive: true } );
	fs.mkdirSync( path.dirname( marker( site ) ), { recursive: true } );
	if ( site.plugin === 'zip' ) {
		fs.mkdirSync( path.join( artifacts, 'zips' ), { recursive: true } );
	}

	wpEnv( site, [ 'start' ], 15 * 60_000 );
	if ( ! ( await isRunning( site ) ) ) {
		throw new Error(
			`wp-env started but ${ site.baseURL } does not respond.`
		);
	}
	fs.writeFileSync( marker( site ), new Date().toISOString() );
	return true;
}

/**
 * Stop the site if setup started it (or E2E_STOP_SITES=1), unless E2E_KEEP_SITES=1.
 *
 * @param {import('../config/env').Site} site Site.
 * @return {boolean} True when the site was stopped.
 */
export function stopIfStarted( site ) {
	const started = fs.existsSync( marker( site ) );
	if (
		process.env.WP_BASE_URL ||
		process.env.E2E_KEEP_SITES === '1' ||
		( ! started && process.env.E2E_STOP_SITES !== '1' )
	) {
		return false;
	}
	wpEnv( site, [ 'stop' ], 5 * 60_000 );
	fs.rmSync( marker( site ), { force: true } );
	return true;
}
