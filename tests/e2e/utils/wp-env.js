/**
 * Start/stop wp-env sites; teardown stops only sites setup started.
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
 * Pinned WordPress/PHP versions ignore the per-run overrides.
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
 * Start the site unless running or reached via WP_BASE_URL. Reusing a running site clears an
 * earlier run's marker (e.g. one left by E2E_KEEP_SITES=1), so teardown leaves it running.
 *
 * @param {import('../config/env').Site} site Site.
 * @return {Promise<boolean>} True when this call started the site.
 */
export async function ensureRunning( site ) {
	if ( process.env.WP_BASE_URL ) {
		return false;
	}
	if ( await isRunning( site ) ) {
		fs.rmSync( marker( site ), { force: true } );
		return false;
	}
	// Must exist before wp-env maps them into the container.
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
 * Stop if setup started it (or E2E_STOP_SITES=1), unless E2E_KEEP_SITES=1.
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
