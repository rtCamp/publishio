import { useState } from 'react';

import { useRenderPreview } from './useRenderPreview';

export function usePatternApp() {
	const [ busy, setBusy ] = useState( false );

	const {
		app,
		previewHtml,
		pending,
		uiState,
		setUiState,
		errorMsg,
		setErrorMsg,
	} = useRenderPreview();

	async function handleInsert() {
		if ( ! pending || ! app ) {
			return;
		}
		setBusy( true );
		try {
			await app.callServerTool( {
				name: 'rtpwai-insert-pattern-confirmed',
				arguments: pending as unknown as Record< string, unknown >,
			} );
			await app.sendMessage( {
				role: 'user',
				content: [
					{
						type: 'text',
						text: 'Pattern inserted successfully. Please continue.',
					},
				],
			} );
			setUiState( 'inserted' );
		} catch ( e: unknown ) {
			setErrorMsg(
				'Insert failed: ' +
					( e instanceof Error ? e.message : String( e ) )
			);
			setUiState( 'error' );
		} finally {
			setBusy( false );
		}
	}

	async function handleAlternative() {
		if ( ! app ) {
			return;
		}
		setBusy( true );
		try {
			await app.sendMessage( {
				role: 'user',
				content: [
					{
						type: 'text',
						text: 'Please generate an alternative pattern design.',
					},
				],
			} );
			setUiState( 'alternative' );
		} catch ( e: unknown ) {
			setErrorMsg( e instanceof Error ? e.message : String( e ) );
			setUiState( 'error' );
		} finally {
			setBusy( false );
		}
	}

	return {
		uiState,
		errorMsg,
		previewHtml,
		pending,
		busy,
		handleInsert,
		handleAlternative,
	};
}
