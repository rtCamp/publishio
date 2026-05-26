/**
 * WordPress dependencies
 */
import { Tooltip } from '@wordpress/components';

/**
 * Internal dependencies
 */
import type { OAuthConnection } from './types';
import { detectApps } from './utils';

const APP_LABELS: Record< string, string > = {
	claude: 'Claude AI',
	openai: 'OpenAI',
	other: 'Other App',
};

interface Props {
	item: OAuthConnection;
}

export function ConnectionFieldName( { item }: Props ) {
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
								className="size-5 shrink-0"
							/>
						</Tooltip>
					) : null
				) }
			</div>
			<span>{ item.client_name }</span>
		</div>
	);
}
