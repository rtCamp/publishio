import { useRef, useState } from 'react';
import {
	useApp,
	useHostStyleVariables,
	type App,
} from '@modelcontextprotocol/ext-apps/react';

import {
	fallbackCard,
	fetchBody,
	fetchCard,
	previewRequest,
	toolName,
} from '../mcp';
import type { Args, BodyState, CardText, PreviewRequest } from '../types';

function isRecord( value: unknown ): value is Args {
	return (
		typeof value === 'object' && value !== null && ! Array.isArray( value )
	);
}

/**
 * Follows the tool call that opened the app. Once the tool succeeds, it asks
 * the server for the card's text and the rendered body at the same time, so
 * the header shows while the theme renders.
 */
export function usePreview() {
	const [ request, setRequest ] = useState< PreviewRequest | null >( null );
	const [ card, setCard ] = useState< CardText | null >( null );
	const [ body, setBody ] = useState< BodyState >( { status: 'loading' } );
	const latest = useRef( 0 );

	function load( host: App, next: PreviewRequest, withCard: boolean ) {
		const run = ++latest.current;
		setBody( { status: 'loading' } );

		if ( withCard ) {
			fetchCard( host, next ).then(
				( text ) => run === latest.current && setCard( text ),
				() => {}
			);
		}

		fetchBody( host, next ).then(
			( rendered ) =>
				run === latest.current &&
				setBody( { status: 'ready', body: rendered } ),
			() => run === latest.current && setBody( { status: 'error' } )
		);
	}

	const { app } = useApp( {
		appInfo: { name: 'publishio-preview', version: '1.3.0' },
		capabilities: {},
		autoResize: true,
		onAppCreated: ( host ) => {
			let input: Args = {};

			host.addEventListener( 'toolinput', ( params ) => {
				input = isRecord( params.arguments ) ? params.arguments : {};
			} );

			host.addEventListener( 'toolresult', ( params ) => {
				if ( params.isError ) {
					return;
				}

				const result = isRecord( params.structuredContent )
					? params.structuredContent
					: {};
				const tool = toolName(
					host.getHostContext()?.toolInfo?.tool.name,
					input
				);
				const next = previewRequest( tool, input, result );

				if ( ! next ) {
					return;
				}

				setRequest( next );
				setCard( fallbackCard( next, result ) );
				load( host, next, true );
			} );
		},
	} );

	useHostStyleVariables( app, app?.getHostContext() );

	function retry() {
		if ( app && request ) {
			load( app, request, true );
		}
	}

	function openEditor() {
		if ( app && card?.editUrl ) {
			void app.openLink( { url: card.editUrl } ).catch( () => {} );
		}
	}

	return { request, card, body, retry, openEditor };
}
