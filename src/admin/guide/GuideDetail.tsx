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

interface GuideDetailProps {
	guide: Guide;
	onBack: () => void;
}

export function GuideDetail( { guide, onBack }: GuideDetailProps ) {
	return (
		<>
			<AdminHeader
				title={ guide.title }
				description={ guide.description }
				actions={
					<Button variant="tertiary" onClick={ onBack }>
						{ __( '← Back', 'rtcamp-publish-with-ai' ) }
					</Button>
				}
			/>
			<p className="p-6 text-sm text-gray-400 italic">
				{ __( 'Steps coming soon.', 'rtcamp-publish-with-ai' ) }
			</p>
		</>
	);
}
