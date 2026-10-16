/**
 * External dependencies
 */
import { execSync } from 'node:child_process';
/**
 * Internal dependencies
 */
import { currentSite } from '../config/env';

/**
 * Run WP-CLI on the current project's site; the timeout stops `wp-env run` hangs.
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
