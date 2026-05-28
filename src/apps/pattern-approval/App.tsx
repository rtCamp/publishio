import { usePatternApp } from './hooks/usePatternApp';
import { McpCard } from './components/McpCard';

export function App() {
	const {
		uiState,
		errorMsg,
		previewHtml,
		pending,
		busy,
		handleInsert,
		handleAlternative,
	} = usePatternApp();

	if ( uiState === 'error' ) {
		return <div className="loading-error">{ errorMsg }</div>;
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
