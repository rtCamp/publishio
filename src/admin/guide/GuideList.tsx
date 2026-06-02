/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { AdminHeader } from '../shared/AdminHeader';
import type { Guide } from './types';
import { GuideCard } from './GuideCard';

const GUIDES: Guide[] = [
	{
		id: 'claude',
		title: 'Claude AI',
		description: __(
			'Learn how to set up Claude AI as your publishing assistant.',
			'rtcamp-publish-with-ai'
		),
		app: 'claude',
	},
];

interface GuideListProps {
	onOpen: ( guide: Guide ) => void;
}

export function GuideList( { onOpen }: GuideListProps ) {
	return (
		<>
			<AdminHeader
				title={ __( 'Guide', 'rtcamp-publish-with-ai' ) }
				description={ __(
					'Step-by-step setup guides for connecting with AI apps.',
					'rtcamp-publish-with-ai'
				) }
			/>
			<div className="flex flex-col gap-3 p-6 max-w-2xl mx-auto w-full">
				{ GUIDES.map( ( guide ) => (
					<GuideCard
						key={ guide.id }
						guide={ guide }
						onClick={ onOpen }
					/>
				) ) }
				<p className="text-sm text-gray-500 text-center pt-2">
					{ __(
						'More guides coming soon.',
						'rtcamp-publish-with-ai'
					) }
				</p>
			</div>
		</>
	);
}
