/**
 * WordPress dependencies
 */
import { ExternalLink } from '@wordpress/components'; // eslint-disable-line @wordpress/use-recommended-components
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { ConnectionMembership, ConnectionUser } from '../../types';

interface Props {
	user: ConnectionUser;
}

function membershipLabel( membership: ConnectionMembership ): string {
	if ( 'super_admin' === membership ) {
		return __( 'Super admin', 'publishio' );
	}

	if ( 'not_member' === membership ) {
		return __( 'Not a member of this site', 'publishio' );
	}

	return '';
}

export function UserField( { user }: Props ) {
	const label = membershipLabel( user.membership );

	return (
		<div className="flex items-center gap-2">
			<img
				src={ user.avatar_url }
				alt={ user.name }
				className="size-8 rounded-full shrink-0"
			/>
			<div className="flex flex-col min-w-0">
				{ user.admin_edit_url ? (
					<ExternalLink
						href={ user.admin_edit_url }
						className="!text-gray-500 hover:!text-gray-600 text-sm truncate"
					>
						{ user.name }
					</ExternalLink>
				) : (
					<span className="text-gray-500 text-sm truncate">
						{ user.name }
					</span>
				) }
				<span className="text-xs text-gray-500 truncate">
					{ user.email }
				</span>
				{ label && (
					<span className="text-xs text-gray-500 truncate">
						{ label }
					</span>
				) }
			</div>
		</div>
	);
}
