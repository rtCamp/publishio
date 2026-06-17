/** A pattern as described by the host tool's arguments / result. */
export interface PatternInput {
	page_id: number;
	position: number;
	pattern_name: string;
	schema: Record< string, unknown >;
}

/** A pattern enriched with the metadata returned by the render tool. */
export interface Pattern extends PatternInput {
	title?: string;
	description?: string;
}

/**
 * The single source of truth for what the UI shows. Each state carries
 * exactly the data that state needs — nothing more.
 */
export type View =
	| { status: 'loading' }
	| { status: 'error'; message: string; context: string }
	| {
			status: 'ready' | 'inserted' | 'alternative';
			pattern: Pattern;
			previewHtml: string;
	  };

/** A view that has a rendered pattern (everything except loading / error). */
export type LoadedView = Extract<
	View,
	{ status: 'ready' | 'inserted' | 'alternative' }
>;
