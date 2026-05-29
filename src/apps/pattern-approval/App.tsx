import { usePatternApp } from './hooks/usePatternApp';
import { McpCard } from './components/McpCard';
import { CardHeader } from './components/CardHeader';

export function App() {
	const {
		uiState,
		errorMsg,
		previewHtml,
		pending,
		busy,
		handleInsert,
		handleAlternative,
		handleAskAi,
	} = usePatternApp();

	if ( uiState === 'error' ) {
		return (
			<div className="w-full bg-(--paper) border border-(--rule) rounded-xl overflow-hidden shadow-sm">
				<CardHeader />
				<div className="px-4 py-12 flex flex-col items-center">
					<h3 className="font-medium text-(--ink-1)">
						Something went wrong
					</h3>
					<p className="text-xs text-(--ink-3) mt-1 break-words">
						{ errorMsg }
					</p>
					<button
						className="mcp-btn mcp-btn-primary mt-3"
						onClick={ () => void handleAskAi() }
					>
						Ask AI for Help
					</button>
				</div>
			</div>
		);
	}

	return (
		<McpCard
			uiState={ uiState }
			pending={ pending }
			previewHtml={ previewHtml }
			busy={ busy }
			onInsert={ () => void handleInsert() }
			onAlternative={ () => void handleAlternative() }
		/>
	);
}
