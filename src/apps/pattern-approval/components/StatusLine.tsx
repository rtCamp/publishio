import { Check, Swap } from './Icons';
import type { UiState, PendingPattern } from '../types';
import { formatPosition } from '../utils';

interface Props {
	uiState: Exclude< UiState, 'loading' | 'error' >;
	pending: PendingPattern | null;
}

export function StatusLine( { uiState, pending }: Props ) {
	const name = pending?.pattern_title ?? pending?.pattern_name ?? 'Pattern';
	const position = formatPosition( pending?.position );

	if ( uiState === 'ready' ) {
		return (
			<span>
				<strong>{ name }</strong> will be inserted at{ ' ' }
				<strong>{ position }</strong>.
			</span>
		);
	}

	const inserted = uiState === 'inserted';
	const accentClass = inserted
		? 'text-(--ok) ring-(--ok)'
		: 'text-(--ink-3) ring-(--ink-3)';
	const message = inserted ? (
		<>
			<strong>{ name }</strong> inserted at <strong>{ position }</strong>.
		</>
	) : (
		<>
			Show me an alternative for this <strong>{ name }</strong> pattern.
		</>
	);

	return (
		<>
			<span
				className={ `w-4 h-4 rounded-full shrink-0 grid place-items-center ring-1 ${ accentClass }` }
			>
				{ inserted ? <Check size={ 11 } /> : <Swap size={ 11 } /> }
			</span>
			<span>{ message }</span>
		</>
	);
}
