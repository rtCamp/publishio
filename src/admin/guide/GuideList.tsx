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
	{
		id: 'openai',
		title: 'OpenAI',
		description: __(
			'Learn how to set up OpenAI (ChatGPT) as your publishing assistant.',
			'rtcamp-publish-with-ai'
		),
		app: 'openai',
	},
	{
		id: 'other',
		title: __( 'Other Apps', 'rtcamp-publish-with-ai' ),
		description: __(
			'Learn how to connect with any compatible AI app.',
			'rtcamp-publish-with-ai'
		),
		app: 'other',
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
			</div>
		</>
	);
}
