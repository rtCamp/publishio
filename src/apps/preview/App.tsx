import { usePreview } from './hooks/usePreview';
import { Card } from './components/Card';

export function App() {
	const { request, card, body, retry, openEditor } = usePreview();

	if ( ! request || ! card ) {
		return null;
	}

	return (
		<Card
			kind={ request.view }
			card={ card }
			body={ body }
			onRetry={ retry }
			onOpen={ openEditor }
		/>
	);
}
