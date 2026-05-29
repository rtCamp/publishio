export type UiState =
	| 'loading'
	| 'ready'
	| 'error'
	| 'inserted'
	| 'alternative';

export interface PreviewData {
	page_id?: number;
	position?: number;
	pattern_name?: string;
	schema?: unknown;
}

export interface PendingPattern {
	page_id: number;
	position: number;
	pattern_name: string;
	pattern_title?: string;
	pattern_description?: string;
	schema: unknown;
}
