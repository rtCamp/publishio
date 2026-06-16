import type { Pattern, View } from './types';

export const initialView: View = { status: 'loading' };

export type Action =
	| { type: 'ready'; pattern: Pattern; previewHtml: string }
	| { type: 'inserted' }
	| { type: 'alternative' }
	| { type: 'error'; message: string; context: string };

export function reduce( view: View, action: Action ): View {
	switch ( action.type ) {
		case 'ready':
			return {
				status: 'ready',
				pattern: action.pattern,
				previewHtml: action.previewHtml,
			};

		case 'inserted':
		case 'alternative':
			return view.status === 'ready'
				? { ...view, status: action.type }
				: view;

		case 'error':
			return {
				status: 'error',
				message: action.message,
				context: action.context,
			};
	}
}
