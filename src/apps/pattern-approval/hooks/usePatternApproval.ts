import { useReducer, useState } from 'react';
import { useApp } from '@modelcontextprotocol/ext-apps/react';
import {
	firstText,
	insertPattern,
	parsePatternInput,
	renderPreview,
} from '../mcp';
import { errorMessage, formatPosition, patternLabel } from '../utils';
import { initialView, reduce } from '../viewState';
import type { PatternInput } from '../types';

const PREVIEW_ERROR_CONTEXT =
	'The AI assistant tried to show a pattern preview but the tool returned an error.';

/**
 * Owns the whole pattern-approval flow: it wires up the MCP host, renders the
 * preview only after the host tool succeeds, and exposes the user actions.
 */
export function usePatternApproval() {
	const [ view, dispatch ] = useReducer( reduce, initialView );
	const [ busy, setBusy ] = useState( false );

	const { app } = useApp( {
		appInfo: { name: 'publishio-pattern-approval', version: '1.0.0' },
		capabilities: {},
		autoResize: true,
		onAppCreated: ( host ) => {
			// The arguments arrive before the tool runs; hold onto them and
			// wait for the result before rendering anything.
			let input: PatternInput | null = null;

			host.addEventListener( 'toolinput', ( params ) => {
				input = parsePatternInput( params.arguments );
			} );

			host.addEventListener( 'toolresult', async ( params ) => {
				if ( params.isError ) {
					dispatch( {
						type: 'error',
						message: firstText( params.content ) ?? 'Unknown error',
						context: PREVIEW_ERROR_CONTEXT,
					} );
					return;
				}

				const pattern =
					parsePatternInput( params.structuredContent ) ?? input;
				if ( ! pattern ) {
					return;
				}

				try {
					const rendered = await renderPreview( host, pattern );
					dispatch( { type: 'ready', ...rendered } );
				} catch ( error ) {
					dispatch( {
						type: 'error',
						message: errorMessage( error ),
						context: `Tried to render a preview for the "${ pattern.pattern_name }" pattern.`,
					} );
				}
			} );
		},
	} );

	/** Runs a user action, surfacing any failure as the error view. */
	async function runAction( context: string, action: () => Promise< void > ) {
		setBusy( true );
		try {
			await action();
		} catch ( error ) {
			dispatch( {
				type: 'error',
				message: errorMessage( error ),
				context,
			} );
		} finally {
			setBusy( false );
		}
	}

	function insert() {
		if ( ! app || view.status !== 'ready' ) {
			return;
		}
		const { pattern } = view;
		const where = formatPosition( pattern.position );

		void runAction(
			`Tried to insert the "${ patternLabel(
				pattern
			) }" pattern at ${ where }.`,
			async () => {
				await insertPattern( app, pattern );
				await app.sendMessage( {
					role: 'user',
					content: [
						{
							type: 'text',
							text: `The "${ patternLabel(
								pattern
							) }" pattern has been successfully inserted at ${ where }. Please continue.`,
						},
					],
				} );
				dispatch( { type: 'inserted' } );
			}
		);
	}

	function requestAlternative() {
		if ( ! app || view.status !== 'ready' ) {
			return;
		}
		const { pattern } = view;

		void runAction(
			`Tried to request an alternative for the "${ patternLabel(
				pattern
			) }" pattern.`,
			async () => {
				await app.sendMessage( {
					role: 'user',
					content: [
						{
							type: 'text',
							text: `Show me an alternative for the "${ patternLabel(
								pattern
							) }" pattern at ${ formatPosition(
								pattern.position
							) }.`,
						},
					],
				} );
				dispatch( { type: 'alternative' } );
			}
		);
	}

	function askAi() {
		if ( ! app || view.status !== 'error' ) {
			return;
		}
		const { message, context } = view;

		// Best-effort: a failed sendMessage here is non-recoverable.
		void app
			.sendMessage( {
				role: 'user',
				content: [
					{
						type: 'text',
						text: `Something went wrong on my end. ${ context }\n\nError: ${ message }\n\nCan you help me figure out what happened and how to proceed?`,
					},
				],
			} )
			.catch( () => {} );
	}

	return { view, busy, insert, requestAlternative, askAi };
}
