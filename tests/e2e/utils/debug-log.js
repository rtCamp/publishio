/**
 * External dependencies
 */
import fs from 'node:fs';
/**
 * Internal dependencies
 */
import { currentSite } from '../config/env';

const PROBLEM = /PHP (Fatal error|Parse error|Warning|Notice|Deprecated)/;

/** Mark the current end of the log; the returned function reads only what was written after it. */
export function markDebugLog() {
	// WP_DEBUG_LOG of the project's site, mapped to the host (see sites.json → debugLog).
	const LOG_PATH = currentSite().debugLogPath;
	const start = fs.existsSync( LOG_PATH ) ? fs.statSync( LOG_PATH ).size : 0;

	return () => {
		if ( ! fs.existsSync( LOG_PATH ) ) {
			return [];
		}
		const size = fs.statSync( LOG_PATH ).size;
		const buf = Buffer.alloc( Math.max( 0, size - start ) );
		const fd = fs.openSync( LOG_PATH, 'r' );
		fs.readSync( fd, buf, 0, buf.length, start );
		fs.closeSync( fd );

		return (
			buf
				.toString( 'utf8' )
				.split( '\n' )
				.filter( ( line ) => PROBLEM.test( line ) )
				// Only fail on problems raised from Publishio's own code.
				.filter( ( line ) => line.includes( '/plugins/publishio/' ) )
		);
	};
}
