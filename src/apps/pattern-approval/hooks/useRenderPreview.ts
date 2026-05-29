import { useState, useEffect } from 'react';
import { useApp } from '@modelcontextprotocol/ext-apps/react';
import type { McpUiToolResultNotification } from '@modelcontextprotocol/ext-apps';

import type { PreviewData, PendingPattern, UiState } from '../types';

type RenderResponse = {
	preview_html: string;
	pattern_title?: string;
	pattern_description?: string;
};

function startHeightReporting(): () => void {
	const report = (): void => {
		const h = Math.min( document.body.scrollHeight, 1200 );
		if ( h > 0 ) {
			window.parent.postMessage(
				{
					jsonrpc: '2.0',
					method: 'ui/notifications/size-changed',
					params: {
						width: document.body.offsetWidth,
						height: h,
					},
				},
				'*'
			);
		}
	};
	report();
	const ro = new ResizeObserver( report );
	ro.observe( document.body );
	return () => ro.disconnect();
}

export function useRenderPreview() {
	const [ toolResult, setToolResult ] = useState<
		McpUiToolResultNotification[ 'params' ] | null
	>( null );
	const [ previewHtml, setPreviewHtml ] = useState( '' );
	const [ pending, setPending ] = useState< PendingPattern | null >( null );
	const [ uiState, setUiState ] = useState< UiState >( 'loading' );
	const [ errorMsg, setErrorMsg ] = useState( '' );
	const [ errorContext, setErrorContext ] = useState( '' );

	const { app } = useApp( {
		appInfo: { name: 'rtpwai-pattern-approval', version: '1.0.0' },
		capabilities: {},
		autoResize: false,
		onAppCreated: ( a ) => {
			a.addEventListener( 'toolresult', ( params ) => {
				setToolResult( params );
			} );
		},
	} );

	useEffect( () => startHeightReporting(), [] );

	useEffect( () => {
		if ( ! toolResult || ! app ) {
			return;
		}

		if ( toolResult.isError ) {
			const first = toolResult.content?.[ 0 ] as
				| { text?: string }
				| undefined;
			const errText = first?.text ?? 'Unknown error';
			setErrorMsg( errText );
			setErrorContext(
				'Claude tried to show a pattern preview but the tool returned an error.'
			);
			setUiState( 'error' );
			return;
		}

		const data = ( toolResult.structuredContent ?? {} ) as PreviewData;
		if ( ! data.pattern_name ) {
			return;
		}

		setPending( {
			page_id: data.page_id ?? 0,
			position: data.position ?? -1,
			pattern_name: data.pattern_name,
			schema: data.schema,
		} );

		const render = async () => {
			const res = await app.callServerTool( {
				name: 'rtpwai-render-pattern',
				arguments: {
					pattern_name: data.pattern_name,
					schema: data.schema as Record< string, unknown >,
				},
			} );

			if ( res.isError ) {
				const msg = (
					res.content?.[ 0 ] as { text?: string } | undefined
				 )?.text;
				throw new Error( msg ?? 'Render failed' );
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
				`Tried to render a preview for the "${ data.pattern_name }" pattern.`
			);
			setUiState( 'error' );
		} );
	}, [ toolResult, app ] );

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
