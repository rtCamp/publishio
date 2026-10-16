/**
 * External dependencies
 */
import fs from 'node:fs';
/**
 * Internal dependencies
 */
import { currentSite } from '../config/env';

const PROBLEM = /PHP (Fatal error|Parse error|Warning|Notice|Deprecated)/;

/** Returns a reader for PHP problems logged after this call. */
export function markDebugLog() {
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
				// Publishio's own code only.
				.filter( ( line ) => line.includes( '/plugins/publishio/' ) )
		);
	};
}
