/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { AdminHeader } from '../shared/AdminHeader';
import type { Guide } from './types';
import { ClaudeGuide } from './ClaudeGuide';
import { OpenAIGuide } from './OpenAIGuide';

interface GuideDetailProps {
	guide: Guide;
	onBack: () => void;
}

function GuideContent( { guide }: { guide: Guide } ) {
	switch ( guide.app ) {
		case 'claude':
			return <ClaudeGuide />;
		case 'openai':
			return <OpenAIGuide />;
	}
}

export function GuideDetail( { guide, onBack }: GuideDetailProps ) {
	return (
		<>
			<AdminHeader
				title={ guide.title }
				description={ guide.description }
				actions={
					<Button variant="tertiary" onClick={ onBack }>
						{ __( '← Back', 'publish-with-ai' ) }
					</Button>
				}
			/>
			<GuideContent guide={ guide } />
		</>
	);
}
