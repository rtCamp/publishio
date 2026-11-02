import type { App } from '@modelcontextprotocol/ext-apps/react';
import type { CallToolResult } from '@modelcontextprotocol/sdk/types.js';

import type {
	Args,
	CardText,
	PreviewBody,
	PreviewRequest,
	Tone,
	ViewKind,
} from './types';

const TONES: Tone[] = [ 'ok', 'info', 'warn', 'muted' ];

export function isRecord( value: unknown ): value is Args {
	return (
		typeof value === 'object' && value !== null && ! Array.isArray( value )
	);
}

function text( value: unknown ): string {
	return typeof value === 'string' ? value : '';
}

function strings( value: unknown ): string[] {
	return Array.isArray( value )
		? value.filter( ( item ): item is string => typeof item === 'string' )
		: [];
}

/** Pulls the first text block out of a tool result's content. */
export function firstText(
	content: CallToolResult[ 'content' ]
): string | undefined {
	const block = content?.[ 0 ];
	return block && block.type === 'text' ? block.text : undefined;
}

/**
 * The Publishio tool that opened the app. Hosts that don't send the tool's
 * name are told apart by its arguments, which differ for each tool.
 */
export function toolName( hostTool: string | undefined, input: Args ): string {
	if ( hostTool ) {
		return hostTool.replace( /^publishio-/, '' );
	}
	if ( 'title' in input && 'synced' in input ) {
		return 'create-pattern';
	}
	if ( 'post_id' in input && 'markup' in input ) {
		return 'insert-blocks';
	}
	if ( 'post_id' in input && 'pattern' in input ) {
		return 'insert-pattern';
	}
	if ( 'pattern' in input && 'markup' in input ) {
		return 'update-pattern';
	}
	return 'post_id' in input ? 'preview-post' : '';
}

/** What to preview after a tool call, or null when the tool doesn't have a preview. */
export function previewRequest(
	tool: string,
	input: Args,
	result: Args
): PreviewRequest | null {
	switch ( tool ) {
		case 'insert-blocks':
		case 'insert-pattern': {
			const paths = strings( result[ 'changed' ] );
			if (
				typeof result[ 'post_id' ] !== 'number' ||
				paths.length === 0
			) {
				return null;
			}
			return {
				view: 'blocks',
				post_id: result[ 'post_id' ],
				paths,
				placement: text( input[ 'placement' ] ) || 'end',
				targeted: text( input[ 'target' ] ).trim() !== '',
				...( tool === 'insert-pattern' && text( input[ 'pattern' ] )
					? { pattern: text( input[ 'pattern' ] ) }
					: {} ),
			};
		}
		case 'create-pattern':
		case 'update-pattern':
			return text( result[ 'name' ] )
				? {
						view: 'pattern',
						pattern: text( result[ 'name' ] ),
						updated: tool === 'update-pattern',
				  }
				: null;
		case 'preview-post':
			return typeof result[ 'post_id' ] === 'number'
				? { view: 'page', post_id: result[ 'post_id' ] }
				: null;
		default:
			return null;
	}
}

const STATUS: Record< ViewKind, ( request: PreviewRequest ) => string > = {
	blocks: () => 'Added',
	pattern: ( request ) => ( request.updated ? 'Updated' : 'Saved' ),
	page: () => '',
};

/** Shown until the server sends the card's text, and if it can't. */
export function fallbackCard(
	request: PreviewRequest,
	result: Args
): CardText {
	const word = STATUS[ request.view ]( request );
	const editUrl = text( result[ 'edit_url' ] ) || null;

	return {
		title: text( result[ 'title' ] ) || firstChangedText( request, result ),
		status: word
			? {
					word,
					tone:
						request.view === 'pattern' && request.updated
							? 'info'
							: 'ok',
			  }
			: null,
		subtitle: '',
		editUrl,
		error: fallbackError( request ),
	};
}

function firstChangedText( request: PreviewRequest, result: Args ): string {
	const outline = isRecord( result[ 'outline' ] ) ? result[ 'outline' ] : {};
	const rows = Array.isArray( outline[ 'rows' ] ) ? outline[ 'rows' ] : [];
	const first = request.paths?.[ 0 ];
	const row = rows.find( ( r ) => isRecord( r ) && r[ 'path' ] === first );
	return isRecord( row ) ? text( row[ 'text' ] ) : '';
}

function fallbackError( request: PreviewRequest ): string {
	switch ( request.view ) {
		case 'blocks':
			return ( request.paths?.length ?? 0 ) === 1
				? "The block was added. Only the preview didn't load."
				: "The blocks were added. Only the preview didn't load.";
		case 'pattern':
			return request.updated
				? "The pattern was updated. Only the preview didn't load."
				: "The pattern was saved. Only the preview didn't load.";
		default:
			return "The page is unchanged. Only the preview didn't load.";
	}
}

async function renderPreview(
	app: App,
	request: PreviewRequest,
	part: 'card' | 'body'
): Promise< Args > {
	const res = await app.callServerTool( {
		name: 'publishio-render-preview',
		arguments: { ...request, part },
	} );

	if ( res.isError || ! isRecord( res.structuredContent ) ) {
		throw new Error( firstText( res.content ) ?? 'The preview failed.' );
	}

	return res.structuredContent;
}

/** The card's header text, from the server. */
export async function fetchCard(
	app: App,
	request: PreviewRequest
): Promise< CardText > {
	const card = await renderPreview( app, request, 'card' );
	const status = isRecord( card[ 'status' ] ) ? card[ 'status' ] : {};
	const tone = TONES.find( ( t ) => t === status[ 'tone' ] ) ?? 'muted';

	return {
		title: text( card[ 'title' ] ),
		status: text( status[ 'word' ] )
			? { word: text( status[ 'word' ] ), tone }
			: null,
		subtitle: text( card[ 'subtitle' ] ),
		editUrl: text( card[ 'edit_url' ] ) || null,
		error: text( card[ 'error' ] ),
	};
}

/** The theme-styled document, and a page's search and social details. */
export async function fetchBody(
	app: App,
	request: PreviewRequest
): Promise< PreviewBody > {
	const body = await renderPreview( app, request, 'body' );
	const search = isRecord( body[ 'search' ] ) ? body[ 'search' ] : null;
	const social = isRecord( body[ 'social' ] ) ? body[ 'social' ] : null;

	return {
		html: text( body[ 'html' ] ),
		...( search && {
			search: {
				address: text( search[ 'address' ] ),
				title: text( search[ 'title' ] ),
				description: text( search[ 'description' ] ) || null,
				hidden: search[ 'hidden' ] === true,
			},
		} ),
		...( social && {
			social: {
				image: text( social[ 'image' ] ) || null,
				site: text( social[ 'site' ] ),
				title: text( social[ 'title' ] ),
				description: text( social[ 'description' ] ) || null,
			},
		} ),
	};
}
