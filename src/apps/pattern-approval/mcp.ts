import type { App } from '@modelcontextprotocol/ext-apps/react';
import type { CallToolResult } from '@modelcontextprotocol/sdk/types.js';

import type { Pattern, PatternInput } from './types';

/** Shape of the `publishio-render-pattern` tool's structured result. */
interface RenderResponse {
	preview_html?: string;
	pattern_title?: string;
	pattern_description?: string;
}

function isRecord( value: unknown ): value is Record< string, unknown > {
	return typeof value === 'object' && value !== null;
}

/** Pulls the first text block out of a tool result's content. */
export function firstText(
	content: CallToolResult[ 'content' ]
): string | undefined {
	const block = content?.[ 0 ];
	return block && block.type === 'text' ? block.text : undefined;
}

/**
 * Validates an unknown payload (tool arguments or structured result) into a
 * {@link PatternInput}, applying sensible defaults. Returns null if it cannot
 * be a pattern at all.
 */
export function parsePatternInput( source: unknown ): PatternInput | null {
	if ( ! isRecord( source ) ) {
		return null;
	}
	const name = source[ 'pattern_name' ];
	const pageId = source[ 'page_id' ];
	const position = source[ 'position' ];
	const schema = source[ 'schema' ];
	if ( typeof name !== 'string' ) {
		return null;
	}
	return {
		page_id: typeof pageId === 'number' ? pageId : 0,
		position: typeof position === 'number' ? position : -1,
		pattern_name: name,
		schema: isRecord( schema ) ? schema : {},
	};
}

/** Renders a pattern preview and returns the enriched pattern + HTML. */
export async function renderPreview(
	app: App,
	input: PatternInput
): Promise< { pattern: Pattern; previewHtml: string } > {
	const res = await app.callServerTool( {
		name: 'publishio-render-pattern',
		arguments: { pattern_name: input.pattern_name, schema: input.schema },
	} );

	if ( res.isError ) {
		throw new Error( firstText( res.content ) ?? 'Render failed' );
	}

	const result = ( res.structuredContent ?? {} ) as RenderResponse;
	const pattern: Pattern = { ...input };
	if ( result.pattern_title ) {
		pattern.title = result.pattern_title;
	}
	if ( result.pattern_description ) {
		pattern.description = result.pattern_description;
	}

	return { pattern, previewHtml: result.preview_html ?? '' };
}

/** Confirms insertion of a pattern. Throws on failure. */
export async function insertPattern(
	app: App,
	pattern: Pattern
): Promise< void > {
	const res = await app.callServerTool( {
		name: 'publishio-insert-pattern-confirmed',
		arguments: {
			page_id: pattern.page_id,
			position: pattern.position,
			pattern_name: pattern.pattern_name,
			schema: pattern.schema,
		},
	} );

	if ( res.isError ) {
		throw new Error( firstText( res.content ) ?? 'Insert failed.' );
	}
}
