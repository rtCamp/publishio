import { Check, Swap } from './Icons';
import { formatPosition, patternLabel } from '../utils';
import type { LoadedView } from '../types';

interface Props {
	view: LoadedView;
}

export function StatusLine( { view }: Props ) {
	const name = patternLabel( view.pattern );
	const position = formatPosition( view.pattern.position );

	if ( view.status === 'ready' ) {
		return (
			<span>
				<strong>{ name }</strong> will be inserted at{ ' ' }
				<strong>{ position }</strong>.
			</span>
		);
	}

	const inserted = view.status === 'inserted';
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
