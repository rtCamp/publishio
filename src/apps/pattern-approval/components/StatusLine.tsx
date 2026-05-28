import { Check, Swap } from './Icons';
import type { UiState } from '../types';

interface Props {
	uiState: Exclude< UiState, 'loading' | 'error' >;
}

export function StatusLine( { uiState }: Props ) {
	if ( uiState === 'ready' ) {
		return <span>Will be inserted as a draft revision.</span>;
	}

	const inserted = uiState === 'inserted';
	const accentClass = inserted
		? 'text-(--ok) ring-(--ok)'
		: 'text-(--ink-3) ring-(--ink-3)';
	const message = inserted
		? 'Inserted as a draft revision.'
		: "I'll generate another direction for you next.";

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
