import { useState, useEffect, useRef } from 'react';
import { useApp } from '@modelcontextprotocol/ext-apps/react';
import type { CallToolResult } from '@modelcontextprotocol/sdk/types.js';

import type { PreviewData, PendingPattern, UiState } from '../types';

type RenderResponse = {
	preview_html: string;
	pattern_title?: string;
	pattern_description?: string;
};

function toPreviewData(
	source: Record< string, unknown > | undefined
): PreviewData | null {
	if ( ! source || typeof source[ 'pattern_name' ] !== 'string' ) {
		return null;
	}
	return source as PreviewData;
}

function firstText( content: CallToolResult[ 'content' ] ): string | undefined {
	const block = content?.[ 0 ];
	return block && block.type === 'text' ? block.text : undefined;
}

export function useRenderPreview() {
	const [ incoming, setIncoming ] = useState< PreviewData | null >( null );
	const [ previewHtml, setPreviewHtml ] = useState( '' );
	const [ pending, setPending ] = useState< PendingPattern | null >( null );
	const [ uiState, setUiState ] = useState< UiState >( 'loading' );
	const [ errorMsg, setErrorMsg ] = useState( '' );
	const [ errorContext, setErrorContext ] = useState( '' );
	const startedRef = useRef( false );

	const { app } = useApp( {
		appInfo: { name: 'publishio-pattern-approval', version: '1.0.0' },
		capabilities: {},
		autoResize: true,
		onAppCreated: ( a ) => {
			a.addEventListener( 'toolinput', ( params ) => {
				const data = toPreviewData( params.arguments );
				if ( data ) {
					setIncoming( data );
				}
			} );
			a.addEventListener( 'toolresult', ( params ) => {
				if ( params.isError ) {
					setErrorMsg(
						firstText( params.content ) ?? 'Unknown error'
					);
					setErrorContext(
						'The AI assistant tried to show a pattern preview but the tool returned an error.'
					);
					setUiState( 'error' );
					return;
				}
				const data = toPreviewData( params.structuredContent );
				if ( data ) {
					setIncoming( ( prev ) => prev ?? data );
				}
			} );
		},
	} );

	useEffect( () => {
		if ( ! incoming || ! app || startedRef.current ) {
			return;
		}
		startedRef.current = true;

		setPending( {
			page_id: incoming.page_id ?? 0,
			position: incoming.position ?? -1,
			pattern_name: incoming.pattern_name as string,
			schema: incoming.schema,
		} );

		const render = async () => {
			const res = await app.callServerTool( {
				name: 'publishio-render-pattern',
				arguments: {
					pattern_name: incoming.pattern_name,
					schema: incoming.schema as Record< string, unknown >,
				},
			} );

			if ( res.isError ) {
				throw new Error( firstText( res.content ) ?? 'Render failed' );
			}

			const sc = res.structuredContent as RenderResponse | undefined;

			setPreviewHtml( sc?.preview_html ?? '' );
			setPending( ( prev ) => {
				if ( ! prev ) {
					return prev;
				}
				const next: PendingPattern = { ...prev };
				if ( sc?.pattern_title ) {
					next.pattern_title = sc.pattern_title;
				}
				if ( sc?.pattern_description ) {
					next.pattern_description = sc.pattern_description;
				}
				return next;
			} );
			setUiState( 'ready' );
		};

		render().catch( ( e: unknown ) => {
			setErrorMsg( e instanceof Error ? e.message : String( e ) );
			setErrorContext(
				`Tried to render a preview for the "${ incoming.pattern_name }" pattern.`
			);
			setUiState( 'error' );
		} );
	}, [ incoming, app ] );

	return {
		app,
		previewHtml,
		pending,
		uiState,
		setUiState,
		errorMsg,
		setErrorMsg,
		errorContext,
		setErrorContext,
	};
}
