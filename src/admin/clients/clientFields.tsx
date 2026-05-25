/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Icon, Tooltip } from '@wordpress/components';
import { globe, lock } from '@wordpress/icons';
import type { Field } from '@wordpress/dataviews';

/**
 * Internal dependencies
 */
import type { OAuthClient } from './types';
import { detectProviders, relativeDate } from './utils';

const PROVIDER_LABELS: Record< string, string > = {
	claude: 'Claude AI',
	openai: 'OpenAI',
	other: 'Other App',
};

const SCOPE_STYLES: Record< string, string > = {
	'mcp:read': 'bg-blue-50 text-blue-700 border border-blue-200',
	'mcp:write': 'bg-violet-50 text-violet-700 border border-violet-200',
};

export const clientFields: Field< OAuthClient >[] = [
	{
		id: 'client_name',
		label: __( 'Name', 'rtcamp-publish-with-ai' ),
		enableSorting: true,
		enableGlobalSearch: true,
		getValue: ( { item } ) => item.client_name,
	},
	{
		id: 'app',
		label: __( 'App', 'rtcamp-publish-with-ai' ),
		getValue: ( { item } ) => item.redirect_uris.join( ' ' ),
		render: ( { item } ) => {
			const logos = window.rtPublishWithAIAdmin?.providerLogos ?? {};
			const providers = detectProviders( item.redirect_uris );
			return (
				<div className="flex items-center gap-1">
					{ providers.map( ( provider ) =>
						logos[ provider ] ? (
							<Tooltip
								key={ provider }
								text={ PROVIDER_LABELS[ provider ] ?? provider }
							>
								<img
									src={ logos[ provider ] }
									alt={
										PROVIDER_LABELS[ provider ] ?? provider
									}
									className="size-6 shrink-0"
								/>
							</Tooltip>
						) : null
					) }
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
		id: 'is_public',
		label: __( 'Type', 'rtcamp-publish-with-ai' ),
		enableSorting: true,
		getValue: ( { item } ) =>
			item.is_public
				? __( 'Public', 'rtcamp-publish-with-ai' )
				: __( 'Confidential', 'rtcamp-publish-with-ai' ),
		render: ( { item } ) => (
			<div className="flex items-center gap-1.5">
				<Icon
					icon={ item.is_public ? globe : lock }
					size={ 20 }
					className="text-gray-500 shrink-0"
				/>
				<span>
					{ item.is_public
						? __( 'Public', 'rtcamp-publish-with-ai' )
						: __( 'Confidential', 'rtcamp-publish-with-ai' ) }
				</span>
			</div>
		),
		elements: [
			{
				value: __( 'Public', 'rtcamp-publish-with-ai' ),
				label: __( 'Public', 'rtcamp-publish-with-ai' ),
			},
			{
				value: __( 'Confidential', 'rtcamp-publish-with-ai' ),
				label: __( 'Confidential', 'rtcamp-publish-with-ai' ),
			},
		],
		filterBy: { operators: [ 'is', 'isNot' ] },
	},
	{
		id: 'scope',
		label: __( 'Scope', 'rtcamp-publish-with-ai' ),
		getValue: ( { item } ) => item.scope,
		render: ( { item } ) => {
			const tokens = item.scope.split( ' ' ).filter( Boolean );
			if ( ! tokens.length ) {
				return <span className="text-gray-400">—</span>;
			}
			return (
				<div className="flex flex-wrap gap-1">
					{ tokens.map( ( token ) => (
						<span
							key={ token }
							className={ `inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
								SCOPE_STYLES[ token ] ??
								'bg-gray-100 text-gray-600 border border-gray-200'
							}` }
						>
							{ token }
						</span>
					) ) }
				</div>
			);
		},
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
