/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { problemFor } from './problems';
import { verdictOf } from './verdict';
import type {
	CheckDefinition,
	CheckResult,
	Exchange,
	RunItems,
	SiteFacts,
} from './types';

interface ReportInput {
	checks: CheckDefinition[];
	items: RunItems;
	facts: SiteFacts | null;
	checkedAt: number;
}

function table( exchanges: Exchange[] ): string[] {
	const withSteps = exchanges.some( ( exchange ) => exchange.step );
	const head = [
		...( withSteps ? [ __( 'Step', 'publishio' ) ] : [] ),
		__( 'Request', 'publishio' ),
		__( 'Expected', 'publishio' ),
		__( 'Got', 'publishio' ),
	];
	const cell = ( text: string ) =>
		text
			.replace( /\\/g, '\\\\' )
			.replace( /\|/g, '\\|' )
			.replace( /\n/g, ' ' );
	const rows = exchanges.map( ( exchange ) => [
		...( withSteps ? [ exchange.step ?? '' ] : [] ),
		exchange.request,
		exchange.expected,
		exchange.got,
	] );

	return [
		`| ${ head.join( ' | ' ) } |`,
		`|${ head.map( () => '---' ).join( '|' ) }|`,
		...rows.map( ( row ) => `| ${ row.map( cell ).join( ' | ' ) } |` ),
		'',
	];
}

function site( facts: SiteFacts | null ): string[] {
	if ( ! facts ) {
		return [ __( "Site details couldn't be loaded.", 'publishio' ), '' ];
	}

	const adapter = facts.mcpAdapter;
	return [
		`- ${ sprintf(
			/* translators: %s: URL */
			__( 'Site address: %s', 'publishio' ),
			facts.homeUrl
		) }`,
		`- ${ sprintf(
			/* translators: %s: URL */
			__( 'WordPress address: %s', 'publishio' ),
			facts.siteUrl
		) }`,
		`- ${ sprintf(
			/* translators: %s: URL */
			__( 'MCP server URL: %s', 'publishio' ),
			facts.mcpUrl
		) }`,
		`- Publishio ${ facts.publishioVersion }, WordPress ${
			facts.wpVersion
		} (${ facts.multisite ? 'multisite' : 'single site' }), PHP ${
			facts.phpVersion
		}`,
		`- ${ sprintf(
			/* translators: %s: plain or pretty */
			__( 'Permalinks: %s', 'publishio' ),
			facts.permalinks
		) }`,
		adapter
			? `- MCP Adapter ${ adapter.version }, ${
					adapter.bundled
						? 'bundled with Publishio'
						: `loaded from ${ adapter.source || 'another plugin' }`
			  }, ${ facts.tools } tools`
			: `- ${ __( 'MCP Adapter not loaded', 'publishio' ) }`,
		'',
	];
}

function problem( check: CheckDefinition, result: CheckResult ): string[] {
	const explained = problemFor( check.id, result.code, result.apps ?? [] );
	const lines = [
		`### ${ 'fail' === result.status ? '✕' : '!' } ${ check.name }`,
		'',
		explained.message,
		'',
	];

	if ( result.exchanges?.length ) {
		lines.push( ...table( result.exchanges ) );
	}
	if ( result.notes?.length ) {
		lines.push( ...result.notes.map( ( note ) => `- ${ note }` ), '' );
	}
	if ( explained.cause ) {
		lines.push(
			`**${ __( 'Likely cause:', 'publishio' ) }** ${ explained.cause }`,
			''
		);
	}
	lines.push(
		`**${ __( 'Fix:', 'publishio' ) }** ${
			explained.technicalFix ?? explained.fix
		}`,
		''
	);
	for ( const snippet of explained.snippets ?? [] ) {
		lines.push( '```', snippet.code, '```', '' );
	}

	return lines;
}

/**
 * The Markdown report people copy for support or an AI app. It holds only addresses, versions and what each check got back.
 *
 * @param input The finished run and the site facts.
 */
export function buildReport( input: ReportInput ): string {
	const { checks, items, facts, checkedAt } = input;
	const verdict = verdictOf( checks, items );
	const done = checks.flatMap( ( check ) => {
		const result = items[ check.id ].result;
		return result ? [ { check, result } ] : [];
	} );
	const problems = done.filter(
		( { result } ) =>
			'fail' === result.status || 'warning' === result.status
	);
	const failures = problems.filter(
		( { result } ) => 'fail' === result.status
	).length;
	const date = new Date( checkedAt ).toLocaleString( 'en-GB', {
		timeZone: 'UTC',
		day: 'numeric',
		month: 'short',
		year: 'numeric',
		hour: '2-digit',
		minute: '2-digit',
	} );

	const lines = [
		`# ${ __( 'Publishio status report', 'publishio' ) }`,
		'',
		`**${ verdict.title }**${
			failures
				? ` (${ sprintf(
						/* translators: %d: number of problems */
						_n(
							'%d problem',
							'%d problems',
							failures,
							'publishio'
						),
						failures
				  ) })`
				: ''
		}`,
		sprintf(
			/* translators: %s: date and time */
			__(
				'Checked %s UTC. Requests to the site were sent without login cookies, from the browser of the person who ran the check.',
				'publishio'
			),
			date
		),
		'',
		`## ${ __( 'Site', 'publishio' ) }`,
		'',
		...site( facts ),
	];

	if ( problems.length ) {
		lines.push( `## ${ __( 'Problems', 'publishio' ) }`, '' );
		problems.forEach( ( { check, result } ) =>
			lines.push( ...problem( check, result ) )
		);
	}

	const skipped = done.filter( ( { result } ) => 'skip' === result.status );
	if ( skipped.length ) {
		lines.push(
			`## ${ __( 'Skipped', 'publishio' ) }`,
			'',
			...skipped.map(
				( { check, result } ) =>
					`- – **${ check.name }:** ${ result.summary }`
			),
			''
		);
	}

	const passed = done.filter( ( { result } ) => 'pass' === result.status );
	if ( passed.length ) {
		lines.push( `## ${ __( 'Passed', 'publishio' ) }`, '' );
		passed.forEach( ( { check, result } ) => {
			lines.push(
				`- ✓ **${ check.name }:** ${ result.summary }`,
				...( result.exchanges ?? [] ).map(
					( exchange ) =>
						`  - ${ exchange.request } → ${ exchange.got }`
				)
			);
		} );
	}

	return lines.join( '\n' ).trim() + '\n';
}
