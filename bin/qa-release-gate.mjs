#!/usr/bin/env node
/**
 * Release gate based on the QA run issue for release/<tag>.
 */
/**
 * External dependencies
 */
import { appendFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

const [ tag, repo = process.env.GITHUB_REPOSITORY ] = process.argv.slice( 2 );
// Commit the tag points at; the QA run must have tested the same code.
const releaseSha = process.env.RELEASE_SHA;

/**
 * Escape a string for use inside a regular expression.
 * @param {string} text Text to match literally.
 * @return {string} The escaped text.
 */
const escapeRegExp = ( text ) => text.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
if ( ! tag || ! repo ) {
	console.error( 'Usage: node bin/qa-release-gate.mjs <tag> [owner/repo]' );
	process.exit( 2 );
}
const branch = `release/${ tag.replace( /^v/, '' ) }`;

const gh = ( args ) =>
	JSON.parse( execFileSync( 'gh', args, { encoding: 'utf8' } ) );
const treeOf = ( sha ) =>
	gh( [ 'api', `repos/${ repo }/commits/${ sha }` ] ).commit.tree.sha;
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

// Most recent run wins.
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

	// The approval only counts for the commit QA tested (qa-execution-runner writes it).
	const testedSha = qa.body.match(
		/^\* \*\*Commit:\*\* ([0-9a-f]{40})\s*$/m
	)?.[ 1 ];
	if ( ! testedSha ) {
		problems.push(
			`QA run #${ qa.number } does not say which commit was tested (a "* **Commit:** <sha>" line).`
		);
	} else if ( releaseSha && treeOf( testedSha ) !== treeOf( releaseSha ) ) {
		// Tags sit on the release PR's merge commit, so compare the code, not the commit.
		problems.push(
			`QA run #${ qa.number } tested ${ testedSha.slice(
				0,
				7
			) }, whose code differs from ${ tag } (${ releaseSha.slice(
				0,
				7
			) }); run QA on the code being released.`
		);
	}

	// HTML comments hold the template's example lines, not blockers.
	const section = (
		qa.body
			.split( /^##\s+🐞 Blockers Found\s*$/m )[ 1 ]
			?.split( /^(?:---|## )/m )[ 0 ] ?? ''
	).replace( /<!--[\s\S]*?-->/g, '' );
	const refs = new Set(
		[
			...section.matchAll(
				new RegExp(
					`(?:#|github\\.com/${ escapeRegExp(
						repo
					) }/issues/)(\\d+)`,
					'g'
				)
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
