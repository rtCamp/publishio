import { useState } from 'react';

import { useRenderPreview } from './useRenderPreview';
import { formatPosition } from '../utils';

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
		errorContext,
		setErrorContext,
	} = useRenderPreview();

	function patternLabel() {
		return pending?.pattern_title ?? pending?.pattern_name ?? 'the pattern';
	}

	function positionLabel() {
		return formatPosition( pending?.position );
	}

	async function handleInsert() {
		if ( ! pending || ! app ) {
			return;
		}
		setBusy( true );
		try {
			const res = await app.callServerTool( {
				name: 'rtpwai-insert-pattern-confirmed',
				arguments: {
					page_id: pending.page_id,
					position: pending.position,
					pattern_name: pending.pattern_name,
					schema: pending.schema,
				},
			} );

			if ( res.isError ) {
				const msg = (
					res.content?.[ 0 ] as { text?: string } | undefined
				 )?.text;
				throw new Error( msg ?? 'Insert failed.' );
			}

			await app.sendMessage( {
				role: 'user',
				content: [
					{
						type: 'text',
						text: `The "${ patternLabel() }" pattern has been successfully inserted at ${ positionLabel() }. Please continue.`,
					},
				],
			} );
			setUiState( 'inserted' );
		} catch ( e: unknown ) {
			const label = patternLabel();
			const pos = positionLabel();
			setErrorMsg( e instanceof Error ? e.message : String( e ) );
			setErrorContext(
				`Tried to insert the "${ label }" pattern at ${ pos }.`
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
						text: `Show me an alternative for the "${ patternLabel() }" pattern at ${ positionLabel() }.`,
					},
				],
			} );
			setUiState( 'alternative' );
		} catch ( e: unknown ) {
			const label = patternLabel();
			setErrorMsg( e instanceof Error ? e.message : String( e ) );
			setErrorContext(
				`Tried to request an alternative for the "${ label }" pattern.`
			);
			setUiState( 'error' );
		} finally {
			setBusy( false );
		}
	}

	async function handleAskAi() {
		if ( ! app ) {
			return;
		}
		const context = errorContext
			? `${ errorContext }\n\nError: ${ errorMsg }`
			: `An error occurred: ${ errorMsg }`;
		try {
			await app.sendMessage( {
				role: 'user',
				content: [
					{
						type: 'text',
						text: `Something went wrong on my end. ${ context } Can you help me figure out what happened and how to proceed?`,
					},
				],
			} );
		} catch {
			// sendMessage failure is non-recoverable; silently ignore.
		}

	return {
		uiState,
		errorMsg,
		previewHtml,
		pending,
		busy,
		handleInsert,
		handleAlternative,
		handleAskAi,
	};
}
