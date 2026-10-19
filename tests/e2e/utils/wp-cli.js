/**
 * External dependencies
 */
import { execFileSync } from 'node:child_process';
/**
 * Internal dependencies
 */
import { currentSite } from '../config/env';

/**
 * Split a command line into words the way a POSIX shell does for plain words and quotes
 * ('single', "double" with \" \\ \$ \` escapes, and backslash escapes outside quotes).
 * No variables, globs or operators: callers pass plain WP-CLI arguments.
 *
 * @param {string} text Command line, e.g. `eval 'echo 1;' --skip-plugins`.
 * @return {string[]} The words.
 */
export function shellWords( text ) {
	const words = [];
	let word = '';
	let started = false;
	let quote = null;
	for ( let i = 0; i < text.length; i++ ) {
		const c = text[ i ];
		if ( quote === "'" ) {
			if ( c === "'" ) {
				quote = null;
			} else {
				word += c;
			}
		} else if ( quote === '"' ) {
			if ( c === '"' ) {
				quote = null;
			} else if (
				c === '\\' &&
				'"\\$`'.includes( text[ i + 1 ] ?? '' )
			) {
				word += text[ ++i ];
			} else {
				word += c;
			}
		} else if ( c === "'" || c === '"' ) {
			quote = c;
			started = true;
		} else if ( c === '\\' && i + 1 < text.length ) {
			word += text[ ++i ];
			started = true;
		} else if ( /\s/.test( c ) ) {
			if ( started ) {
				words.push( word );
				word = '';
				started = false;
			}
		} else {
			word += c;
			started = true;
		}
	}
	if ( quote ) {
		throw new Error( `Unclosed ${ quote } in: ${ text }` );
	}
	if ( started ) {
		words.push( word );
	}
	return words;
}

/**
 * Run WP-CLI on the current project's site; the timeout stops `wp-env run` hangs.
 * Runs without a shell: the command and arguments are passed to the process directly.
 *
 * @param {string} args WP-CLI arguments, e.g. "plugin list".
 * @return {string} The command's output, trimmed.
 */
export function wpCli( args ) {
	const [ command, ...base ] = shellWords( currentSite().wpCli );
	return execFileSync( command, [ ...base, ...shellWords( args ) ], {
		encoding: 'utf8',
		stdio: [ 'ignore', 'pipe', 'pipe' ],
		timeout: 120_000,
	} ).trim();
}
