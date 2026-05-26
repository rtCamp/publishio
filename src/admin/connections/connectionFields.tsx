/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Tooltip } from '@wordpress/components';
import type { Field } from '@wordpress/dataviews';

/**
 * Internal dependencies
 */
import type { OAuthConnection } from './types';
import { detectApps, relativeDate } from './utils';

const APP_LABELS: Record< string, string > = {
	claude: 'Claude AI',
	openai: 'OpenAI',
	other: 'Other App',
};

export const connectionFields: Field< OAuthConnection >[] = [
	{
		id: 'client_name',
		label: __( 'Name', 'rtcamp-publish-with-ai' ),
		enableSorting: true,
		enableGlobalSearch: true,
		getValue: ( { item } ) => item.client_name,
		render: ( { item } ) => {
			const logos = window.rtPublishWithAIAdmin?.appLogos ?? {};
			const apps = detectApps( item.redirect_uris );
			return (
				<div className="flex items-center gap-2">
					<div className="flex items-center gap-1 shrink-0">
						{ apps.map( ( app ) =>
							logos[ app ] ? (
								<Tooltip
									key={ app }
									text={ APP_LABELS[ app ] ?? app }
								>
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
		},
	},
	{
		id: 'client_id',
		label: __( 'Client ID', 'rtcamp-publish-with-ai' ),
		getValue: ( { item } ) => item.client_id,
	},
	{
		id: 'registered_at',
		label: __( 'Registered', 'rtcamp-publish-with-ai' ),
		enableSorting: true,
		getValue: ( { item } ) =>
			new Date( item.registered_at * 1000 ).toISOString(),
		render: ( { item } ) => (
			<time
				dateTime={ new Date( item.registered_at * 1000 ).toISOString() }
				title={ new Date( item.registered_at * 1000 ).toLocaleString() }
			>
				{ relativeDate( item.registered_at ) }
			</time>
		),
	},
];
