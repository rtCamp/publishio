#!/usr/bin/env node
/**
 * Stop this project's E2E wp-env sites (those in tests/e2e/config/sites.json) if any are still
 * running; exit non-zero if some remain. Other wp-env projects on the machine are left alone.
 */
/**
 * External dependencies
 */
import { basename } from 'node:path';
import { readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';

const { sites } = JSON.parse(
	readFileSync( 'tests/e2e/config/sites.json', 'utf8' )
);
const shell = process.platform === 'win32';

/** Docker Compose project of each site: the folder wp-env installs it in. */
const projects = Object.values( sites ).map( ( site ) => {
	const res = spawnSync(
		'npx',
		[ 'wp-env', `--config=${ site.wpEnvConfig }`, 'status', '--json' ],
		{ encoding: 'utf8', shell }
	);
	const installPath = JSON.parse( res.stdout || '{}' ).installPath;
	if ( res.status !== 0 || ! installPath ) {
		console.error(
			`Could not read wp-env status for ${ site.wpEnvConfig }:\n${ res.stderr }`
		);
		process.exit( 1 );
	}
	return { site, project: basename( installPath ) };
} );

const running = ( project ) => {
	const res = spawnSync(
		'docker',
		[
			'ps',
			'--filter',
			`label=com.docker.compose.project=${ project }`,
			'--format',
			'{{.Names}}',
		],
		{ encoding: 'utf8' }
	);
	if ( res.status !== 0 ) {
		console.error( res.stderr || 'docker ps failed' );
		process.exit( 1 );
	}
	return res.stdout.split( '\n' ).filter( Boolean );
};

const up = projects.filter( ( { project } ) => running( project ).length );
if ( ! up.length ) {
	console.log( 'All WordPress environments are down.' );
	process.exit( 0 );
}

console.log(
	`Still running:\n  ${ up
		.map( ( p ) => p.project )
		.join( '\n  ' ) }\nStopping…`
);
for ( const { site } of up ) {
	spawnSync( 'npx', [ 'wp-env', `--config=${ site.wpEnvConfig }`, 'stop' ], {
		stdio: 'inherit',
		shell,
	} );
}

const left = up.flatMap( ( { project } ) => running( project ) );
if ( left.length ) {
	spawnSync( 'docker', [ 'stop', ...left ], { stdio: 'inherit' } );
}
if ( up.some( ( { project } ) => running( project ).length ) ) {
	console.error( 'Some WordPress environments are still running.' );
	process.exit( 1 );
}
console.log( 'All WordPress environments are down.' );
