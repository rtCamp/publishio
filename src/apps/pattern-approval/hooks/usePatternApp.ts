import { useState, useRef, useEffect } from 'react';
import { useApp } from '@modelcontextprotocol/ext-apps/react';
import type { McpUiToolResultNotification } from '@modelcontextprotocol/ext-apps';

import type { PreviewData, PendingPattern } from '../types';

function observeFrameHeight( frame: HTMLIFrameElement ): void {
	const doc = frame.contentDocument;
	if ( ! doc ) {
		return;
	}
	const report = (): void => {
		const h = Math.min( doc.documentElement.scrollHeight, 1200 );
		if ( h > 0 ) {
			window.parent.postMessage(
				{
					jsonrpc: '2.0',
					method: 'ui/notifications/size-changed',
					params: {
						width: document.documentElement.offsetWidth,
						height: h,
					},
				},
				'*'
			);
		}
	};
	report();
	new ResizeObserver( report ).observe( doc.body );
}

export function usePatternApp() {
	const [ toolResult, setToolResult ] = useState<
		McpUiToolResultNotification[ 'params' ] | null
	>( null );
	const [ previewHtml, setPreviewHtml ] = useState( '' );
	const [ pending, setPending ] = useState< PendingPattern | null >( null );
	const [ uiState, setUiState ] = useState< 'loading' | 'ready' | 'error' >(
		'loading'
	);
	const [ errorMsg, setErrorMsg ] = useState( '' );
	const [ status, setStatus ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const frameRef = useRef< HTMLIFrameElement >( null );

	const { app } = useApp( {
		appInfo: { name: 'rtpwai-pattern-approval', version: '1.0.0' },
		capabilities: {},
		// observeFrameHeight reports the inner-iframe content height instead
		autoResize: false,
		onAppCreated: ( a ) => {
			a.addEventListener( 'toolresult', ( params ) => {
				setToolResult( params );
			} );
		},
	} );

	// Load preview when tool result arrives
	useEffect( () => {
		if ( ! toolResult || ! app ) {
			return;
		}

		if ( toolResult.isError ) {
			const first = toolResult.content?.[ 0 ] as
				| { text?: string }
				| undefined;
			setErrorMsg(
				'Preview failed: ' + ( first?.text ?? 'Unknown error' )
			);
			setUiState( 'error' );
			return;
		}

		const data = ( toolResult.structuredContent ?? {} ) as PreviewData;
		if ( ! data.pattern_name ) {
			return;
		}

		setPending( {
			post_id: data.post_id,
			position: data.position,
			pattern_name: data.pattern_name,
			schema: data.schema,
		} );

		void app
			.callServerTool( {
				name: 'rtpwai-render-pattern',
				arguments: {
					pattern_name: data.pattern_name,
					schema: data.schema as Record< string, unknown >,
				},
			} )
			.then( ( res ) => {
				if ( res.isError ) {
					const first = res.content?.[ 0 ] as
						| { text?: string }
						| undefined;
					throw new Error( first?.text ?? 'Render failed' );
				}
				setPreviewHtml(
					( res.structuredContent?.[ 'preview_html' ] as
						| string
						| undefined ) ?? ''
				);
				setUiState( 'ready' );
			} )
			.catch( ( e: unknown ) => {
				setErrorMsg(
					'Preview failed: ' +
						( e instanceof Error ? e.message : String( e ) )
				);
				setUiState( 'error' );
			} );
	}, [ toolResult, app ] );

	// Observe inner-iframe height once preview is ready
	useEffect( () => {
		if ( uiState !== 'ready' || ! frameRef.current ) {
			return;
		}
		const frame = frameRef.current;
		frame.addEventListener( 'load', () => observeFrameHeight( frame ), {
			once: true,
		} );
	}, [ uiState ] );

	async function handleInsert() {
		if ( ! pending || ! app ) {
			return;
		}
		setBusy( true );
		setStatus( 'Inserting…' );
		try {
			await app.callServerTool( {
				name: 'rtpwai-insert-pattern-confirmed',
				arguments: pending as Record< string, unknown >,
			} );
			setStatus( pending.pattern_name + ' inserted ✓' );
			await app.sendMessage( {
				role: 'user',
				content: [
					{
						type: 'text',
						text: 'Pattern inserted successfully. Please continue.',
					},
				],
			} );
		} catch ( e: unknown ) {
			setStatus(
				'Error: ' + ( e instanceof Error ? e.message : String( e ) )
			);
			setBusy( false );
		}
	}

	async function handleCancel() {
		if ( ! app ) {
			return;
		}
		setBusy( true );
		setStatus( 'Cancelling…' );
		try {
			await app.sendMessage( {
				role: 'user',
				content: [
					{
						type: 'text',
						text: 'User cancelled the pattern insertion. Do not insert this pattern.',
					},
				],
			} );
			setStatus( 'Cancelled' );
		} catch ( e: unknown ) {
			setStatus( e instanceof Error ? e.message : String( e ) );
			setBusy( false );
		}
	}

	return {
		uiState,
		errorMsg,
		previewHtml,
		status,
		busy,
		frameRef,
		handleInsert,
		handleCancel,
	};
}
