export type UiState =
	| 'loading'
	| 'ready'
	| 'error'
	| 'inserted'
	| 'alternative';

export interface PreviewData {
	post_id?: unknown;
	position?: unknown;
	pattern_name?: string;
	schema?: unknown;
}

export interface PendingPattern {
	post_id: unknown;
	position: unknown;
	pattern_name: string;
	pattern_title?: string;
	pattern_description?: string;
	schema: unknown;
}
