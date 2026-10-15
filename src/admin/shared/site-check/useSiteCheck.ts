/**
 * WordPress dependencies
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { siteCheckApi } from './api';
import { CHECKS } from './checks';
import { initialItems, runChecks } from './runner';
import { loadCheck, saveCheck } from './storage';
import { verdictOf } from './verdict';
import type { RunItems, SiteFacts } from './types';

interface Options {
	/** Run on mount. */
	autoRun: boolean;
	/** Show the run kept in this browser instead, while it's fresh. */
	reuseSaved?: boolean;
}

/**
 * Run the site checks and track their progress. Every finished run is kept in this browser for the Guide page.
 *
 * @param options The hook's options.
 */
export function useSiteCheck( options: Options ) {
	const { autoRun, reuseSaved = false } = options;
	const version = window.publishioAdmin?.pluginVersion ?? '';
	const mcpUrl = window.publishioAdmin?.mcpServerUrl ?? '';
	const tokenUrl = window.publishioAdmin?.tokenUrl ?? '';

	const [ saved ] = useState( () =>
		reuseSaved ? loadCheck( CHECKS, version, mcpUrl ) : null
	);
	const [ items, setItems ] = useState< RunItems >(
		() => saved?.items ?? initialItems( CHECKS )
	);
	const [ running, setRunning ] = useState( () => autoRun && ! saved );
	const [ startedAt, setStartedAt ] = useState< number | null >( null );
	const [ checkedAt, setCheckedAt ] = useState< number | null >(
		saved?.checkedAt ?? null
	);
	const [ facts, setFacts ] = useState< SiteFacts | null >( null );
	const runId = useRef( 0 );

	const start = useCallback( () => {
		const id = ++runId.current;
		const isCurrent = () => id === runId.current;
		const factsRequest = siteCheckApi.facts().catch( () => null );

		factsRequest.then( ( loaded ) => isCurrent() && setFacts( loaded ) );
		setItems( initialItems( CHECKS ) );
		setRunning( true );
		setStartedAt( Date.now() );
		setCheckedAt( null );

		runChecks(
			CHECKS,
			{
				mcpUrl,
				tokenUrl,
				origin: window.location.origin,
				facts: () => factsRequest,
				apps: siteCheckApi.apps,
			},
			( next ) => isCurrent() && setItems( next )
		).then( ( final ) => {
			if ( ! isCurrent() ) {
				return;
			}
			const finished = Date.now();
			setRunning( false );
			setCheckedAt( finished );
			saveCheck( version, mcpUrl, final, finished );
		} );
	}, [ mcpUrl, tokenUrl, version ] );

	const invalidate = useCallback( () => {
		runId.current++;
	}, [] );

	useEffect( () => {
		if ( autoRun && ! saved ) {
			start();
		}
		return invalidate;
	}, [ autoRun, saved, start, invalidate ] );

	return {
		items,
		verdict: verdictOf( CHECKS, items ),
		running,
		idle: ! running && null === checkedAt,
		startedAt,
		checkedAt,
		facts,
		start,
	};
}

export type SiteCheck = ReturnType< typeof useSiteCheck >;
