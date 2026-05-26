/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { OAuthCredential } from './types';

interface DeleteCredentialDialogProps {
	credential: OAuthCredential;
	onConfirm: () => void;
	onCancel: undefined | ( () => void );
}

export function DeleteCredentialDialog( {
	credential,
	onConfirm,
	onCancel = () => {},
}: DeleteCredentialDialogProps ) {
	return (
		<>
			<p className="text-sm text-gray-700 mt-0">
				{ sprintf(
					/* translators: %s: credential name */
					__(
						'Are you sure you want to delete "%s"?',
						'rtcamp-publish-with-ai'
					),
					credential.client_name
				) }
			</p>
			<p className="text-sm text-gray-700">
				{ __(
					'This will immediately sign out all active sessions using this credential. Any integrations built with this client ID and secret will stop working.',
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
					{ __( 'Delete Credential', 'rtcamp-publish-with-ai' ) }
				</Button>
				<Button variant="tertiary" onClick={ onCancel }>
					{ __( 'Cancel', 'rtcamp-publish-with-ai' ) }
				</Button>
			</div>
		</>
	);
}
