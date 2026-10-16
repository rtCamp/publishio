/**
 * External dependencies
 */
import { execSync } from 'node:child_process';
/**
 * Internal dependencies
 */
import { currentSite } from '../config/env';

/**
 * Run a WP-CLI command against the site of the current test's project.
 *
 * Each call goes through `wp-env run` (docker exec), which can occasionally lose track of a
 * finished command; the timeout makes that fail the step instead of hanging the whole run.
 *
 * @param {string} args WP-CLI arguments, e.g. "plugin list".
 * @return {string} The command's output, trimmed.
 */
export function wpCli( args ) {
	return execSync( `${ currentSite().wpCli } ${ args }`, {
		encoding: 'utf8',
		stdio: [ 'ignore', 'pipe', 'pipe' ],
		timeout: 120_000,
	} ).trim();
}
