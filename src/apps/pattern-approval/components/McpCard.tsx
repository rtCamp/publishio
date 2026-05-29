import { CardHeader } from './CardHeader';
import { PatternMeta } from './PatternMeta';
import { PatternPreview } from './PatternPreview';
import { StatusLine } from './StatusLine';
import { ActionButtons } from './ActionButtons';
import type { UiState, PendingPattern } from '../types';
import { LoadingPreview } from './LoadingPreview';

interface Props {
	uiState: UiState;
	pending: PendingPattern | null;
	previewHtml: string;
	busy: boolean;
	onInsert: () => void;
	onAlternative: () => void;
}

export function McpCard( {
	uiState,
	pending,
	previewHtml,
	busy,
	onInsert,
	onAlternative,
}: Props ) {
	const isLoading = uiState === 'loading';
	const isResult = uiState === 'inserted' || uiState === 'alternative';

	const patternName = pending?.pattern_title ?? pending?.pattern_name ?? '';

	return (
		<div className="w-full bg-(--paper) border border-(--rule) rounded-xl overflow-hidden shadow-sm">
			<CardHeader />
			<div className="p-3">
				{ ! isLoading && (
					<PatternMeta
						name={ patternName }
						description={ pending?.pattern_description }
					/>
				) }

				{ isLoading ? (
					<LoadingPreview />
				) : (
					<PatternPreview html={ previewHtml } />
				) }

				<div className="mt-3 pt-3 border-t border-(--rule-soft) flex items-center justify-between gap-2 flex-wrap">
					<div className="flex items-center gap-2 text-xs text-(--ink-3)">
						{ ! isLoading && (
							<StatusLine
								uiState={
									uiState as Exclude<
										UiState,
										'loading' | 'error'
									>
								}
								pending={ pending }
							/>
						) }
					</div>
					<ActionButtons
						disabled={ isLoading || isResult || busy }
						onInsert={ onInsert }
						onAlternative={ onAlternative }
					/>
				</div>
			</div>
		</div>
	);
}
