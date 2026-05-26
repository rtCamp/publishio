/**
 * WordPress dependencies
 */
import { __experimentalConfirmDialog as ConfirmDialog } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { OAuthConnection } from './types';

interface DeleteConfirmDialogProps {
	connection: OAuthConnection;
	onConfirm: () => void;
	onCancel: () => void;
}

export function DeleteConfirmDialog( {
	connection,
	onConfirm,
	onCancel,
}: DeleteConfirmDialogProps ) {
	return (
		<ConfirmDialog isOpen onConfirm={ onConfirm } onCancel={ onCancel }>
			{ sprintf(
				/* translators: %s: connection name */
				__(
					'Are you sure you want to delete "%s"? This action cannot be undone.',
					'rtcamp-publish-with-ai'
				),
				connection.client_name
			) }
		</ConfirmDialog>
	);
}
