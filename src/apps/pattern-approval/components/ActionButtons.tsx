import { Check, Swap } from './Icons';

interface Props {
	disabled: boolean;
	onInsert: () => void;
	onAlternative: () => void;
}

export function ActionButtons( { disabled, onInsert, onAlternative }: Props ) {
	return (
		<div className="flex gap-2">
			<button
				className="mcp-btn mcp-btn-ghost"
				disabled={ disabled }
				onClick={ onAlternative }
			>
				<Swap /> Show alternative
			</button>
			<button
				className="mcp-btn mcp-btn-primary"
				disabled={ disabled }
				onClick={ onInsert }
			>
				<Check size={ 14 } /> Insert pattern
			</button>
		</div>
	);
}
