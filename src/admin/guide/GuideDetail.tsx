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
						{ __( '← Back', 'publishio' ) }
					</Button>
				}
			/>
			<GuideContent guide={ guide } />
			<p className="mx-auto -mt-6 mb-10 box-border w-full max-w-2xl px-4 md:px-6 text-sm text-gray-500">
				{ __(
					"If the connection fails, the Status page shows what's wrong.",
					'publishio'
				) }{ ' ' }
				<a
					href={ window.publishioAdmin?.statusUrl ?? '' }
					className="text-blue-600 underline hover:text-blue-800"
				>
					{ __( 'Open Status', 'publishio' ) }
				</a>
			</p>
		</>
	);
}
