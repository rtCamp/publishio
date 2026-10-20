/**
 * External dependencies
 */
import { execSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
/**
 * Internal dependencies
 */
import { artifacts, settings } from '../config/env';

/** Host path of the zip under test. */
export const zipPath = () => settings.zip;

/** Host folder the install sites map to wp-content/publishio-e2e-zips. */
const mappedZips = path.join( artifacts, 'zips' );

/**
 * Zip path inside the wp-env container. Without `file`, the zip under test is copied into the
 * mapped folder first when PUBLISHIO_ZIP points elsewhere, so WP-CLI installs that exact zip.
 * @param {string} [file] File name inside the mapped folder.
 */
export function zipPathInContainer( file ) {
	if ( ! file ) {
		const zip = zipPath();
		file = path.basename( zip );
		if (
			path.resolve( path.dirname( zip ) ) !== path.resolve( mappedZips )
		) {
			// A separate name, so a built artifacts/zips/publishio.zip is never overwritten.
			file = `selected-${ file }`;
			fs.mkdirSync( mappedZips, { recursive: true } );
			fs.copyFileSync( zip, path.join( mappedZips, file ) );
		}
	}
	return `/var/www/html/wp-content/publishio-e2e-zips/${ file }`;
}

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

/** Plugin header of the checked-out branch. */
export const branchHeader = () =>
	parseHeader(
		fs.readFileSync( path.join( process.cwd(), 'publishio.php' ), 'utf8' )
	);

/**
 * Zip of the raw source (no vendor/ or build/), next to the real zip.
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
