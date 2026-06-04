/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { OAuthConnection } from './types';
import { Notice } from '../shared/Notice';

interface DeleteConnectionDialogProps {
	connection: OAuthConnection;
	onConfirm: () => Promise< void >;
	onCancel: undefined | ( () => void );
}

export function DeleteConnectionDialog( {
	connection,
	onConfirm,
	onCancel = () => {},
}: DeleteConnectionDialogProps ) {
	const [ isDeleting, setIsDeleting ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );

	async function handleConfirm() {
		setIsDeleting( true );
		setError( null );
		try {
			await onConfirm();
		} catch {
			setError(
				__(
					'Failed to delete connection. Please try again.',
					'publish-with-ai'
				)
			);
		} finally {
			setIsDeleting( false );
		}
	}

	return (
		<>
			{ error && (
				<Notice status="error" className="mb-4">
					{ error }
				</Notice>
			) }
			<p className="text-sm text-gray-700 mt-0">
				{ sprintf(
					/* translators: 1: user name, 2: app name */
					__(
						'Are you sure you want to revoke %1$s\'s access to "%2$s"?',
						'publish-with-ai'
					),
					connection.user.name,
					connection.client_name
				) }
			</p>
			<p className="text-sm text-gray-700">
				{ __(
					'This will immediately sign out this user. They will need to reconnect the next time they use this app.',
					'publish-with-ai'
				) }
			</p>
			<p className="text-sm font-medium text-red-600">
				{ __( 'This action cannot be undone.', 'publish-with-ai' ) }
			</p>

			<div className="flex justify-end gap-2 mt-6 pt-4 border-t border-gray-200">
				<Button
					variant="primary"
					isDestructive
					isBusy={ isDeleting }
					disabled={ isDeleting }
					onClick={ handleConfirm }
				>
					{ __( 'Revoke Access', 'publish-with-ai' ) }
				</Button>
				<Button
					variant="tertiary"
					onClick={ onCancel }
					disabled={ isDeleting }
				>
					{ __( 'Cancel', 'publish-with-ai' ) }
				</Button>
			</div>
		</>
	);
}
