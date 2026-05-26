/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { ExternalLink, Tooltip } from '@wordpress/components'; // eslint-disable-line @wordpress/use-recommended-components

/**
 * Internal dependencies
 */
import type { ConnectionUser } from './types';

function UserProfileLink( {
	href,
	children,
}: {
	href: string;
	children: React.ReactNode;
} ) {
	return (
		<ExternalLink
			href={ href }
			className="!text-gray-500 hover:!text-gray-600 text-sm truncate"
		>
			{ children }
		</ExternalLink>
	);
}

interface SingleUserRowProps {
	user: ConnectionUser;
	avatarSize?: string;
	showEmail?: boolean;
}

function SingleUserRow( {
	user,
	avatarSize = 'size-8',
	showEmail = true,
}: SingleUserRowProps ) {
	return (
		<div className="flex items-center gap-2">
			<img
				src={ user.avatar_url }
				alt={ user.name }
				className={ `${ avatarSize } rounded-full shrink-0` }
			/>
			<div className="flex flex-col min-w-0">
				<UserProfileLink href={ user.admin_edit_url }>
					{ user.name }
				</UserProfileLink>
				{ showEmail && (
					<span className="text-xs text-gray-500 truncate">
						{ user.email }
					</span>
				) }
			</div>
		</div>
	);
}

interface MultiUserRowProps {
	users: ConnectionUser[];
}

function MultiUserRow( { users }: MultiUserRowProps ) {
	const visibleAvatars = users.slice( 0, 2 );
	const overflowAvatars = users.slice( 2 );
	const visibleNames = users.slice( 0, 2 );
	const nameOverflow = users.length - 2;

	return (
		<div className="flex flex-col gap-1">
			<div className="flex items-center gap-1 shrink-0">
				{ visibleAvatars.map( ( user ) => (
					<Tooltip
						key={ user.id }
						text={ `${ user.name } <${ user.email }>` }
					>
						<img
							src={ user.avatar_url }
							alt={ user.name }
							className="size-6 rounded-full"
						/>
					</Tooltip>
				) ) }
				{ overflowAvatars.length > 0 && (
					<Tooltip
						text={ overflowAvatars
							.map( ( u ) => u.name )
							.join( ', ' ) }
					>
						<span className="size-6 rounded-full bg-gray-100 text-gray-600 text-xs flex items-center justify-center font-medium">
							+{ overflowAvatars.length }
						</span>
					</Tooltip>
				) }
			</div>
			<div className="flex items-center gap-1 min-w-0 flex-wrap">
				{ visibleNames.map( ( user, i ) => (
					<span
						key={ user.id }
						className="flex items-center gap-1 min-w-0"
					>
						<UserProfileLink href={ user.admin_edit_url }>
							{ user.name }
						</UserProfileLink>
						{ i < visibleNames.length - 1 && (
							<span className="text-gray-300 text-xs">,</span>
						) }
					</span>
				) ) }
				{ nameOverflow > 0 && (
					<span className="text-xs text-gray-500">
						+{ nameOverflow }{ ' ' }
						{ __( 'more', 'rtcamp-publish-with-ai' ) }
					</span>
				) }
			</div>
		</div>
	);
}

interface Props {
	users: ConnectionUser[];
}

export function ConnectionFieldUsers( { users }: Props ) {
	if ( ! users.length ) {
		return <span className="text-gray-400">—</span>;
	}

	if ( users.length === 1 ) {
		return <SingleUserRow user={ users[ 0 ]! } />;
	}

	return <MultiUserRow users={ users } />;
}
