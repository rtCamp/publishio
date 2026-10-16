#!/usr/bin/env node
/**
 * One entry point for every E2E project, locally and in CI.
 *
 *   node bin/e2e.mjs <project> start [wp-env args]    start the project's WordPress site
 *   node bin/e2e.mjs <project> test  [playwright args] run the project (its site setup runs first)
 *   node bin/e2e.mjs <project> cli   <wp-cli args>     run WP-CLI on the project's site
 *   node bin/e2e.mjs <project> stop | clean            stop, or reset the site's database
 *
 * Projects and sites live in tests/e2e/config/sites.json. Sites without a pinned version
 * follow env: WP_ENV_CORE="WordPress/WordPress#7.0" WP_ENV_PHP_VERSION=8.3 E2E_THEME=ollie
 */
/**
 * External dependencies
 */
import { mkdirSync, readFileSync } from 'node:fs';
import { dirname } from 'node:path';
import { spawnSync } from 'node:child_process';

const { sites, projects } = JSON.parse(
	readFileSync( 'tests/e2e/config/sites.json', 'utf8' )
);
const [ name, command = 'test', ...rest ] = process.argv.slice( 2 );

if ( ! projects[ name ] ) {
	console.error(
		`Usage: node bin/e2e.mjs <${ Object.keys( projects ).join(
			'|'
		) }> <start|test|cli|stop|clean> [args]`
	);
	process.exit( 1 );
}

const site = sites[ projects[ name ].site ];
const wpEnv = [ 'wp-env', `--config=${ site.wpEnvConfig }` ];

const env = { ...process.env };
// A site that pins its WordPress/PHP version must not be moved by the per-run overrides.
if ( site.wp ) {
	delete env.WP_ENV_CORE;
}
if ( site.php ) {
	delete env.WP_ENV_PHP_VERSION;
}

const run = ( args ) => {
	const result = spawnSync( 'npx', args, {
		stdio: 'inherit',
		env,
		shell: process.platform === 'win32',
	} );
	process.exit( result.status ?? 1 );
};

switch ( command ) {
	case 'start':
		mkdirSync( dirname( site.debugLog ), { recursive: true } );
		mkdirSync( 'artifacts/zips', { recursive: true } );
		// --update applies WP_ENV_CORE / WP_ENV_PHP_VERSION changes to an existing site.
		run( [ ...wpEnv, 'start', '--update', ...rest ] );
		break;
	case 'test':
		run( [ 'playwright', 'test', `--project=${ name }`, ...rest ] );
		break;
	case 'cli':
		run( [ ...wpEnv, 'run', 'cli', 'wp', ...rest ] );
		break;
	case 'stop':
		run( [ ...wpEnv, 'stop' ] );
		break;
	case 'clean':
		run( [ ...wpEnv, 'clean', 'all' ] );
		break;
	default:
		console.error( `Unknown command "${ command }".` );
		process.exit( 1 );
}
