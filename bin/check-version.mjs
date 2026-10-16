#!/usr/bin/env node
/**
 * Check every declared plugin version (and the tag) agree.
 */
/**
 * External dependencies
 */
import fs from 'node:fs';

const read = ( f ) => fs.readFileSync( f, 'utf8' );
const sources = {
	'publishio.php (Version header)': read( 'publishio.php' ).match(
		/^\s*\*\s*Version:\s*(\S+)/m
	)?.[ 1 ],
	'publishio.php (PUBLISHIO_VERSION)': read( 'publishio.php' ).match(
		/define\(\s*'PUBLISHIO_VERSION',\s*'([^']+)'/
	)?.[ 1 ],
	'readme.txt (Stable tag)':
		read( 'readme.txt' ).match( /^Stable tag:\s*(\S+)/m )?.[ 1 ],
	'composer.json (version)': JSON.parse( read( 'composer.json' ) ).version,
};

const ref =
	process.argv[ 2 ] ??
	( process.env.GITHUB_REF_TYPE === 'tag'
		? process.env.GITHUB_REF_NAME
		: undefined );
if ( ref ) {
	sources[ 'git tag' ] = ref.replace( /^v/, '' );
}

const values = new Set( Object.values( sources ) );
for ( const [ where, v ] of Object.entries( sources ) ) {
	console.log( `${ ( v ?? 'MISSING' ).padEnd( 12 ) } ${ where }` );
}
if ( values.size !== 1 || values.has( undefined ) ) {
	console.error(
		'\n::error::Plugin version mismatch. Update every file listed above to the same version.'
	);
	process.exit( 1 );
}
console.log( `\nVersion ${ [ ...values ][ 0 ] } is consistent.` );
