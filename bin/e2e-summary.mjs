#!/usr/bin/env node
/**
 * Prints a Markdown table of E2E results per project from a Playwright JSON report.
 *
 *   node bin/e2e-summary.mjs [artifacts/results.json]
 */
/**
 * External dependencies
 */
import fs from 'node:fs';

const report = JSON.parse(
	fs.readFileSync( process.argv[ 2 ] ?? 'artifacts/results.json', 'utf8' )
);
const rows = {};

const outcome = ( t ) => {
	if ( t.status === 'unexpected' ) {
		return 'failed';
	}
	if ( t.status === 'flaky' ) {
		return 'flaky';
	}
	return t.results.at( -1 )?.status === 'skipped' ? 'skipped' : 'passed';
};

const walk = ( suite ) => {
	for ( const spec of suite.specs ?? [] ) {
		for ( const t of spec.tests ) {
			if ( /^(setup|teardown)-/.test( t.projectName ) ) {
				continue;
			}
			rows[ t.projectName ] ??= {
				passed: 0,
				failed: 0,
				flaky: 0,
				skipped: 0,
			};
			rows[ t.projectName ][ outcome( t ) ]++;
		}
	}
	( suite.suites ?? [] ).forEach( walk );
};
report.suites.forEach( walk );

const lines = [
	'### E2E results',
	'',
	'| Project | Passed | Failed | Flaky | Skipped |',
	'| --- | --- | --- | --- | --- |',
	...Object.entries( rows ).map(
		( [ project, r ] ) =>
			`| ${ project } | ${ r.passed } | ${ r.failed } | ${ r.flaky } | ${ r.skipped } |`
	),
	'',
	'Full report: download the **e2e-report** artifact and open `index.html`.',
];

console.log( lines.join( '\n' ) );
