/**
 * WordPress dependencies
 */
import { ExternalLink } from '@wordpress/components'; // eslint-disable-line @wordpress/use-recommended-components

/**
 * Internal dependencies
 */
import type { ConnectionUser } from '../../types';

interface Props {
	user: ConnectionUser;
}

export function UserField( { user }: Props ) {
	return (
		<div className="flex items-center gap-2">
			<img
				src={ user.avatar_url }
				alt={ user.name }
				className="size-8 rounded-full shrink-0"
			/>
			<div className="flex flex-col min-w-0">
				<ExternalLink
					href={ user.admin_edit_url }
					className="!text-gray-500 hover:!text-gray-600 text-sm truncate"
				>
					{ user.name }
				</ExternalLink>
				<span className="text-xs text-gray-500 truncate">
					{ user.email }
				</span>
			</div>
		</div>
	);
}
