import { usePatternApproval } from './hooks/usePatternApproval';
import { McpCard } from './components/McpCard';
import { ErrorCard } from './components/ErrorCard';

export function App() {
	const { view, busy, insert, requestAlternative, askAi } =
		usePatternApproval();

	if ( view.status === 'error' ) {
		return <ErrorCard message={ view.message } onAskAi={ askAi } />;
	}

	return (
		<McpCard
			view={ view }
			busy={ busy }
			onInsert={ insert }
			onAlternative={ requestAlternative }
		/>
	);
}
