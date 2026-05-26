/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { OAuthConnection } from './types';

interface DeleteConnectionDialogProps {
	connection: OAuthConnection;
	onConfirm: () => void;
	onCancel: undefined | ( () => void );
}

export function DeleteConnectionDialog( {
	connection,
	onConfirm,
	onCancel = () => {},
}: DeleteConnectionDialogProps ) {
	return (
		<>
			<p className="text-sm text-gray-700 mt-0">
				{ sprintf(
					/* translators: %s: connection name */
					__(
						'Are you sure you want to delete "%s"?',
						'rtcamp-publish-with-ai'
					),
					connection.client_name
				) }
			</p>
			<p className="text-sm text-gray-700">
				{ __(
					'This will immediately sign out every user who is connected via this app. They will need to reconnect the next time they use it.',
					'rtcamp-publish-with-ai'
				) }
			</p>
			<p className="text-sm font-medium text-red-600">
				{ __(
					'This action cannot be undone.',
					'rtcamp-publish-with-ai'
				) }
			</p>

			<div className="flex justify-end gap-2 mt-6 pt-4 border-t border-gray-200">
				<Button variant="primary" isDestructive onClick={ onConfirm }>
					{ __( 'Delete Connection', 'rtcamp-publish-with-ai' ) }
				</Button>
				<Button variant="tertiary" onClick={ onCancel }>
					{ __( 'Cancel', 'rtcamp-publish-with-ai' ) }
				</Button>
			</div>
		</>
	);
}
