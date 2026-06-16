import { CardHeader } from './CardHeader';
import { PatternMeta } from './PatternMeta';
import { PatternPreview } from './PatternPreview';
import { StatusLine } from './StatusLine';
import { ActionButtons } from './ActionButtons';
import { LoadingPreview } from './LoadingPreview';
import { patternLabel } from '../utils';
import type { View } from '../types';

interface Props {
	view: Exclude< View, { status: 'error' } >;
	busy: boolean;
	onInsert: () => void;
	onAlternative: () => void;
}

export function McpCard( { view, busy, onInsert, onAlternative }: Props ) {
	const loaded = view.status === 'loading' ? null : view;
	const isResult = loaded !== null && loaded.status !== 'ready';

	return (
		<div className="w-full bg-(--paper)">
			<CardHeader />
			<div className="p-3">
				{ loaded && (
					<PatternMeta
						name={ patternLabel( loaded.pattern ) }
						description={ loaded.pattern.description }
					/>
				) }

				{ loaded ? (
					<PatternPreview html={ loaded.previewHtml } />
				) : (
					<LoadingPreview />
				) }

				<div className="mt-3 pt-3 border-t border-(--rule-soft) flex items-center justify-between gap-2 flex-wrap">
					<div className="flex items-center gap-2 text-xs text-(--ink-3)">
						{ loaded && <StatusLine view={ loaded } /> }
					</div>
					<ActionButtons
						disabled={ ! loaded || isResult || busy }
						onInsert={ onInsert }
						onAlternative={ onAlternative }
					/>
				</div>
			</div>
		</div>
	);
}
