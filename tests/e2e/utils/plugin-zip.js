/**
 * External dependencies
 */
import { execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
/**
 * Internal dependencies
 */
import { settings } from '../config/env';

/** Path of the zip under test on the host (set by the workflow or bin/get-plugin-zip.sh). */
export const zipPath = () => settings.zip;

/**
 * Same zip as seen from inside the wp-env container (mapped in .wp-env.install.json).
 * @param {string} file Path of the file.
 */
export const zipPathInContainer = ( file = path.basename( zipPath() ) ) =>
	`/var/www/html/wp-content/publishio-e2e-zips/${ file }`;

export function zipEntries( zip = zipPath() ) {
	return execSync( `unzip -Z1 "${ zip }"`, { encoding: 'utf8' } )
		.split( '\n' )
		.filter( Boolean );
}

export function readFromZip( entry, zip = zipPath() ) {
	return execSync( `unzip -p "${ zip }" "${ entry }"`, { encoding: 'utf8' } );
}

export function parseHeader( php ) {
	const get = ( key ) =>
		php
			.match( new RegExp( `^\\s*\\*\\s*${ key }:\\s*(.+)$`, 'm' ) )?.[ 1 ]
			.trim() ?? '';
	return {
		name: get( 'Plugin Name' ),
		uri: get( 'Plugin URI' ),
		description: get( 'Description' ),
		version: get( 'Version' ),
		author: get( 'Author' ),
		authorUri: get( 'Author URI' ),
		requiresWp: get( 'Requires at least' ),
		requiresPhp: get( 'Requires PHP' ),
	};
}

/** The header of the branch under test (the checked-out repo). */
export const branchHeader = () =>
	parseHeader(
		fs.readFileSync( path.join( process.cwd(), 'publishio.php' ), 'utf8' )
	);

/**
 * Build a zip of the raw source (what GitHub's "Download ZIP" gives you): no vendor/ or build/.
 * Written next to the real zip so the container can see it.
 */
export function buildSourceZip() {
	const out = path.join( path.dirname( zipPath() ), 'publishio-source.zip' );
	execSync(
		`git archive --format=zip --prefix=publishio/ -o "${ out }" HEAD`,
		{ stdio: 'ignore' }
	);
	return out;
}

/**
 * -1, 0 or 1, comparing dotted version strings.
 * @param {string} a Version.
 * @param {string} b Version to compare with.
 */
export function compareVersions( a, b ) {
	const pa = a.split( '.' ).map( Number );
	const pb = b.split( '.' ).map( Number );
	for ( let i = 0; i < Math.max( pa.length, pb.length ); i++ ) {
		const d = ( pa[ i ] ?? 0 ) - ( pb[ i ] ?? 0 );
		if ( d ) {
			return d > 0 ? 1 : -1;
		}
	}
	return 0;
}
