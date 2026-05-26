/**
 * WordPress dependencies
 */
import { Tooltip } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { detectApps } from '../../utils';

const APP_LABELS: Record< string, string > = {
	claude: 'Claude AI',
	openai: 'OpenAI',
	other: 'Other App',
};

interface Props {
	item: { client_name: string; redirect_uris: string[] };
}

export function NameField( { item }: Props ) {
	const logos = window.rtPublishWithAIAdmin?.appLogos ?? {};
	const apps = detectApps( item.redirect_uris );

	return (
		<div className="flex items-center gap-2">
			<div className="flex items-center gap-1 shrink-0">
				{ apps.map( ( app ) =>
					logos[ app ] ? (
						<Tooltip key={ app } text={ APP_LABELS[ app ] ?? app }>
							<img
								src={ logos[ app ] }
								alt={ APP_LABELS[ app ] ?? app }
								className="size-6 shrink-0"
							/>
						</Tooltip>
					) : null
				) }
			</div>
			<span className="font-medium">{ item.client_name }</span>
		</div>
	);
}
