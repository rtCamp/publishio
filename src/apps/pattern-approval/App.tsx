import { usePatternApp } from './hooks/usePatternApp';
import { PreviewView } from './components/PreviewView';

export function App() {
	const {
		uiState,
		errorMsg,
		previewHtml,
		status,
		busy,
		frameRef,
		handleInsert,
		handleCancel,
	} = usePatternApp();

	return (
		<div className="approval-ui">
			{ uiState === 'loading' && (
				<div id="loading">
					<div className="spinner" />
				</div>
			) }
			{ uiState === 'error' && <div id="loading">{ errorMsg }</div> }
			{ uiState === 'ready' && (
				<PreviewView
					previewHtml={ previewHtml }
					status={ status }
					busy={ busy }
					frameRef={ frameRef }
					onInsert={ () => void handleInsert() }
					onCancel={ () => void handleCancel() }
				/>
			) }
		</div>
	);
}
