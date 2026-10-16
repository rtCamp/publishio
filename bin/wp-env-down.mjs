#!/usr/bin/env node
/**
 * Confirm every E2E WordPress environment is down; stop any that are still running.
 *
 *   node bin/wp-env-down.mjs
 *
 * Teardown projects normally stop the sites. This is the safety net run at the end of every CI
 * job (also after failures and timeouts): it stops each site in tests/e2e/config/sites.json with
 * wp-env, then stops any leftover wp-env container directly, and exits non-zero only if
 * something is still running after that.
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

// Anything wp-env did not stop (e.g. a config that is no longer in sites.json).
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
