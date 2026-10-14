/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { AdminHeader } from '../shared/AdminHeader';
import type { SiteCheck } from '../shared/site-check/useSiteCheck';
import type { Guide } from './types';
import { GuideCard } from './GuideCard';
import { ConnectCard } from './ConnectCard';

const GUIDES: Guide[] = [
	{
		id: 'claude',
		title: 'Claude AI',
		description: __(
			'Learn how to set up Claude AI as your publishing assistant.',
			'publishio'
		),
		app: 'claude',
	},
	{
		id: 'openai',
		title: 'ChatGPT',
		description: __(
			'Learn how to set up ChatGPT as your publishing assistant.',
			'publishio'
		),
		app: 'openai',
	},
];

interface GuideListProps {
	onOpen: ( guide: Guide ) => void;
	siteCheck: SiteCheck;
}

export function GuideList( { onOpen, siteCheck }: GuideListProps ) {
	return (
		<>
			<AdminHeader
				title={ __( 'Guide', 'publishio' ) }
				description={ __(
					'Step-by-step setup guides for connecting with AI apps.',
					'publishio'
				) }
			/>
			<div className="box-border flex flex-col gap-3 p-4 md:p-6 max-w-2xl mx-auto w-full">
				<ConnectCard siteCheck={ siteCheck } />
				<h2 className="m-0 mt-3 text-[11px] font-semibold uppercase tracking-wide text-gray-500">
					{ __( 'Setup guides', 'publishio' ) }
				</h2>
				{ GUIDES.map( ( guide ) => (
					<GuideCard
						key={ guide.id }
						guide={ guide }
						onClick={ onOpen }
					/>
				) ) }
				<p className="text-sm text-gray-500 text-center pt-2">
					{ __( 'More guides coming soon.', 'publishio' ) }
				</p>
			</div>
		</>
	);
}
