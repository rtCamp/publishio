/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { APP_IDS, appNames, problemFor } from './problems';
import type { AppId, CheckDefinition, RunItems } from './types';

export type Tone = 'running' | 'success' | 'warning' | 'error' | 'blocked';

export interface Verdict {
	tone: Tone;
	title: string;
	text: string;
}

/**
 * The overall answer: the first of not ready, can't check, ready with limits, or ready.
 *
 * @param checks Checks in the order they're shown.
 * @param items  Their current state.
 */
export function verdictOf(
	checks: CheckDefinition[],
	items: RunItems
): Verdict {
	const done = checks.filter( ( check ) => items[ check.id ].result ).length;
	if ( done < checks.length ) {
		return {
			tone: 'running',
			title: __( 'Checking your site…', 'publishio' ),
			text: sprintf(
				/* translators: 1: checks done, 2: all checks */
				__( '%1$d of %2$d checks done', 'publishio' ),
				done,
				checks.length
			),
		};
	}

	const failed = checks.find(
		( { id } ) => 'fail' === items[ id ].result?.status
	);
	const failure = failed && items[ failed.id ].result;
	if ( failed && failure ) {
		return {
			tone: 'error',
			title: __( 'Not ready', 'publishio' ),
			text: problemFor( failed.id, failure.code, failure.apps ?? [] )
				.message,
		};
	}

	if ( 'other_origin' === items.mcp.result?.code ) {
		// Skipped checks show no fix, so the verdict carries it.
		const blocked = problemFor( 'mcp', 'other_origin' );
		return {
			tone: 'blocked',
			title: __( "Can't check from this page", 'publishio' ),
			text: `${ blocked.message } ${ blocked.fix }`,
		};
	}

	// A local address leaves only Claude Code, which runs on the same computer or network. Of those, only apps whose identity the site could check can connect.
	const local = 'warning' === items.address.result?.status;
	const appsResult = items.apps.result;
	const failedApps = ( appsResult?.apps ?? [] ).map( ( app ) => app.id );
	const working = (
		local ? ( [ 'claude_code' ] as AppId[] ) : APP_IDS
	).filter( ( id ) => ! failedApps.includes( id ) );
	const appsText = appsResult
		? problemFor( 'apps', appsResult.code, appsResult.apps ?? [] ).message
		: '';

	if ( ! working.length ) {
		return {
			tone: 'error',
			title: __( 'Not ready', 'publishio' ),
			text: appsText,
		};
	}
	if ( working.length < APP_IDS.length ) {
		return {
			tone: 'warning',
			title: sprintf(
				/* translators: %s: names of the apps that can connect */
				__( 'Ready for %s only', 'publishio' ),
				appNames( working )
			),
			text: local
				? problemFor( 'address', items.address.result?.code ?? '' )
						.message
				: appsText,
		};
	}

	return {
		tone: 'success',
		title: __( 'Ready', 'publishio' ),
		text: __( 'AI apps can connect to this site.', 'publishio' ),
	};
}
