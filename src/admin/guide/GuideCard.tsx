/**
 * WordPress dependencies
 */
import { Button, Card, CardBody } from '@wordpress/components'; // eslint-disable-line @wordpress/use-recommended-components
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { Guide } from './types';

interface GuideCardProps {
	guide: Guide;
	onClick: ( guide: Guide ) => void;
}

export function GuideCard( { guide, onClick }: GuideCardProps ) {
	const logoUrl = window.rtPublishWithAIAdmin?.appLogos?.[ guide.app ];

	return (
		<Card>
			<CardBody className="flex flex-row items-center gap-4">
				{ logoUrl && (
					<img
						src={ logoUrl }
						alt=""
						aria-hidden="true"
						className="w-10 h-10 shrink-0"
					/>
				) }
				<div className="flex flex-col gap-1 flex-1 min-w-0">
					<h3 className="m-0 text-sm font-semibold text-gray-900">
						{ guide.title }
					</h3>
					<p className="m-0 text-xs text-gray-500 leading-relaxed">
						{ guide.description }
					</p>
				</div>
				<Button
					variant="tertiary"
					onClick={ () => onClick( guide ) }
					className="shrink-0"
				>
					{ __( 'Open Guide →', 'rtcamp-publish-with-ai' ) }
				</Button>
			</CardBody>
		</Card>
	);
}
