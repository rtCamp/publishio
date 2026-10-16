#!/usr/bin/env node
/**
 * Release gate tied to the qa-management QA run issue (see .github/workflows/qa-execution-runner.yml).
 *
 *   node bin/qa-release-gate.mjs <tag> [owner/repo]
 *
 * Passes only when the QA run issue for release/<tag>:
 *   - exists, is closed, carries `qa-passed` and not `qa-blocked`, and
 *   - every bug linked under "Blockers Found" that is still open is P2 or P3 (deferred).
 *     An open P0/P1, or an open bug without a priority label, blocks the release.
 * Needs the GitHub CLI with read access to issues (GH_TOKEN in CI).
 */
/**
 * External dependencies
 */
import { appendFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

const [ tag, repo = process.env.GITHUB_REPOSITORY ] = process.argv.slice( 2 );
if ( ! tag || ! repo ) {
	console.error( 'Usage: node bin/qa-release-gate.mjs <tag> [owner/repo]' );
	process.exit( 2 );
}
const branch = `release/${ tag.replace( /^v/, '' ) }`;

const gh = ( args ) =>
	JSON.parse( execFileSync( 'gh', args, { encoding: 'utf8' } ) );
const labelsOf = ( issue ) => issue.labels.map( ( l ) => l.name );
const priorityOf = ( labels ) => {
	for ( const label of labels ) {
		const m = label.match( /^(?:priority:\s*)?P([0-3])$/i );
		if ( m ) {
			return Number( m[ 1 ] );
		}
	}
	return null;
};

const problems = [];
const lines = [ `### QA release gate for \`${ branch }\`` ];

const candidates = gh( [
	'issue',
	'list',
	'--repo',
	repo,
	'--state',
	'all',
	'--label',
	'qa-testing',
	'--search',
	`"${ branch }" in:title`,
	'--limit',
	'50',
	'--json',
	'number,title,state,labels,body,url',
] ).filter( ( i ) => i.title.endsWith( `— ${ branch }` ) );

// The most recent run wins (a re-run after fixes opens a new issue).
const qa = candidates.sort( ( a, b ) => b.number - a.number )[ 0 ];

if ( ! qa ) {
	problems.push(
		`No QA run issue titled "… — ${ branch }" with the qa-testing label. Push ${ branch } so qa-execution-runner opens it, or open one from the QA Release Run template.`
	);
} else {
	lines.push(
		`QA run issue: [#${ qa.number }](${
			qa.url
		}) (${ qa.state.toLowerCase() })`
	);
	const labels = labelsOf( qa );
	if ( qa.state !== 'CLOSED' ) {
		problems.push( `QA run #${ qa.number } is still open.` );
	}
	if ( labels.includes( 'qa-blocked' ) ) {
		problems.push( `QA run #${ qa.number } is labelled qa-blocked.` );
	}
	if ( ! labels.includes( 'qa-passed' ) ) {
		problems.push( `QA run #${ qa.number } is not labelled qa-passed.` );
	}

	// Bugs linked in the "Blockers Found" section: #123 or a full issue URL in this repo.
	const section =
		qa.body
			.split( /^##\s+🐞 Blockers Found\s*$/m )[ 1 ]
			?.split( /^(?:---|## )/m )[ 0 ] ?? '';
	const refs = new Set(
		[
			...section.matchAll(
				new RegExp( `(?:#|github\\.com/${ repo }/issues/)(\\d+)`, 'g' )
			),
		].map( ( m ) => Number( m[ 1 ] ) )
	);
	const deferred = [];
	for ( const number of refs ) {
		const bug = gh( [
			'issue',
			'view',
			String( number ),
			'--repo',
			repo,
			'--json',
			'number,title,state,labels',
		] );
		if ( bug.state !== 'OPEN' ) {
			continue;
		}
		const priority = priorityOf( labelsOf( bug ) );
		if ( priority === 2 || priority === 3 ) {
			deferred.push( `#${ bug.number } (P${ priority }) ${ bug.title }` );
		} else {
			problems.push(
				`Blocker #${ bug.number } "${ bug.title }" is open and ${
					priority === null
						? 'has no priority label'
						: `is P${ priority }`
				}; only P2/P3 bugs may be deferred.`
			);
		}
	}
	lines.push(
		`Linked blockers: ${ refs.size }; deferred (open P2/P3): ${ deferred.length }`
	);
	deferred.forEach( ( d ) => lines.push( `- deferred: ${ d }` ) );
}

lines.push( problems.length ? '**Blocked:**' : '**Passed.**' );
problems.forEach( ( p ) => lines.push( `- ${ p }` ) );
console.log( lines.join( '\n' ) );
if ( process.env.GITHUB_STEP_SUMMARY ) {
	appendFileSync(
		process.env.GITHUB_STEP_SUMMARY,
		lines.join( '\n' ) + '\n'
	);
}
process.exit( problems.length ? 1 : 0 );
