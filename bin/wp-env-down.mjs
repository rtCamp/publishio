#!/usr/bin/env node
/**
 * Stop any E2E wp-env sites still running; exit non-zero if some remain.
 */
/**
 * External dependencies
 */
import { readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';

const { sites } = JSON.parse(
	readFileSync( 'tests/e2e/config/sites.json', 'utf8' )
);

const running = () => {
	const res = spawnSync(
		'docker',
		[ 'ps', '--filter', 'name=wp-env-', '--format', '{{.Names}}' ],
		{ encoding: 'utf8' }
	);
	if ( res.status !== 0 ) {
		console.error( res.stderr || 'docker ps failed' );
		process.exit( 1 );
	}
	return res.stdout.split( '\n' ).filter( Boolean );
};

let containers = running();
if ( ! containers.length ) {
	console.log( 'All WordPress environments are down.' );
	process.exit( 0 );
}

console.log( `Still running:\n  ${ containers.join( '\n  ' ) }\nStopping…` );
for ( const site of Object.values( sites ) ) {
	spawnSync( 'npx', [ 'wp-env', `--config=${ site.wpEnvConfig }`, 'stop' ], {
		stdio: 'inherit',
		shell: process.platform === 'win32',
	} );
}

containers = running();
if ( containers.length ) {
	spawnSync( 'docker', [ 'stop', ...containers ], { stdio: 'inherit' } );
}

containers = running();
if ( containers.length ) {
	console.error( `Could not stop:\n  ${ containers.join( '\n  ' ) }` );
	process.exit( 1 );
}
console.log( 'All WordPress environments are down.' );
