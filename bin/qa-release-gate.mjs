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

/**
 * Escape a string for use inside a regular expression.
 * @param {string} text Text to match literally.
 * @return {string} The escaped text.
 */
const escapeRegExp = ( text ) => text.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );

/**
 * Text outside HTML comments; an unclosed `<!--` hides the rest, as on GitHub.
 * @param {string} text Markdown.
 * @return {string} The visible text.
 */
const withoutComments = ( text ) => {
	let visible = '';
	let from = 0;
	for (
		let start = text.indexOf( '<!--' );
		start !== -1;
		start = text.indexOf( '<!--', from )
	) {
		visible += text.slice( from, start );
		const end = text.indexOf( '-->', start + 4 );
		if ( end === -1 ) {
			return visible;
		}
		from = end + 3;
	}
	return visible + text.slice( from );
};
if ( ! tag || ! repo ) {
	console.error( 'Usage: node bin/qa-release-gate.mjs <tag> [owner/repo]' );
	process.exit( 2 );
}
const branch = `release/${ tag.replace( /^v/, '' ) }`;

const gh = ( args ) =>
	JSON.parse( execFileSync( 'gh', args, { encoding: 'utf8' } ) );
const treeOf = ( ref ) =>
	gh( [ 'api', `repos/${ repo }/commits/${ encodeURIComponent( ref ) }` ] )
		.commit.tree.sha;
// Commit the tag points at (release.yml passes it; otherwise the tag itself).
const releaseRef = process.env.RELEASE_SHA || tag;
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
	// Only text GitHub shows counts: HTML comments hold the template's examples.
	const body = withoutComments( qa.body.replace( /\r\n/g, '\n' ) );
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
	const testedShas = [
		...body.matchAll( /^\* \*\*Commit:\*\* ([0-9a-f]{40})\s*$/gm ),
	].map( ( m ) => m[ 1 ] );
	if ( testedShas.length !== 1 ) {
		problems.push(
			`QA run #${ qa.number } must say which commit was tested in exactly one "* **Commit:** <sha>" line (found ${ testedShas.length }).`
		);
	} else if ( treeOf( testedShas[ 0 ] ) !== treeOf( releaseRef ) ) {
		// Tags sit on the release PR's merge commit, so compare the code, not the commit.
		problems.push(
			`QA run #${ qa.number } tested ${ testedShas[ 0 ].slice(
				0,
				7
			) }, whose code differs from ${ tag }; run QA on the code being released.`
		);
	}

	const parts = body.split( /^##\s+🐞 Blockers Found\s*$/m );
	if ( parts.length !== 2 ) {
		problems.push(
			`QA run #${
				qa.number
			} needs exactly one "## 🐞 Blockers Found" heading (found ${
				parts.length - 1
			}).`
		);
	}
	// Code spans are examples too, so drop them before reading issue references.
	const section = ( parts[ 1 ]?.split( /^(?:---|## )/m )[ 0 ] ?? '' )
		.split( '`' )
		.filter( ( _, i ) => i % 2 === 0 )
		.join( ' ' );
	// "#12" or a link to an issue in this repo; not "other/repo#12", "#12abc" or URL anchors.
	const refs = new Set(
		[
			...section.matchAll(
				new RegExp(
					`(?:(?<![\\w/.#-])#|https://github\\.com/${ escapeRegExp(
						repo
					) }/issues/)(\\d+)(?![\\w-])`,
					'g'
				)
			),
		].map( ( m ) => Number( m[ 1 ] ) )
	);
	const deferred = [];
	for ( const number of refs ) {
		let bug;
		try {
			bug = gh( [
				'issue',
				'view',
				String( number ),
				'--repo',
				repo,
				'--json',
				'number,title,state,labels',
			] );
		} catch {
			problems.push(
				`Blocker #${ number } is not an issue in ${ repo } (a pull request, or no such issue).`
			);
			continue;
		}
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
