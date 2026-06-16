import { CardHeader } from './CardHeader';

interface Props {
	message: string;
	onAskAi: () => void;
}

export function ErrorCard( { message, onAskAi }: Props ) {
	return (
		<div className="w-full bg-(--paper)">
			<CardHeader />
			<div className="px-4 py-12 flex flex-col items-center">
				<h3 className="font-medium text-(--ink)">
					Something went wrong
				</h3>
				<p className="text-xs text-(--ink-3) mt-1 break-words">
					{ message }
				</p>
				<button
					className="mcp-btn mcp-btn-primary mt-3"
					onClick={ onAskAi }
				>
					Ask AI for Help
				</button>
			</div>
		</div>
	);
}
