/**
 * WordPress dependencies
 */
import { ExternalLink, Tooltip } from '@wordpress/components'; // eslint-disable-line @wordpress/use-recommended-components

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
	item: {
		client_name: string;
		redirect_uris: string[];
		client_uri?: string | null;
		logo_uri?: string | null;
	};
}

export function NameField( { item }: Props ) {
	const logos = window.rtPublishWithAIAdmin?.appLogos ?? {};

	const logoEl = item.logo_uri ? (
		<img
			src={ item.logo_uri }
			alt={ item.client_name }
			className="size-6 shrink-0 object-contain"
		/>
	) : (
		<>
			{ detectApps( item.redirect_uris ).map( ( app ) =>
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
		</>
	);

	const nameEl = item.client_uri ? (
		<ExternalLink
			href={ item.client_uri }
			className="font-medium text-gray-500 hover:text-gray-600"
			onClick={ ( e: React.MouseEvent ) => e.stopPropagation() }
		>
			{ item.client_name }
		</ExternalLink>
	) : (
		<span className="font-medium text-gray-500">{ item.client_name }</span>
	);

	return (
		<div className="flex items-center gap-2">
			<div className="flex items-center gap-1 shrink-0">{ logoEl }</div>
			{ nameEl }
		</div>
	);
}
