/**
 * WordPress dependencies
 */
import { __experimentalConfirmDialog as ConfirmDialog } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { OAuthClient } from './types';

interface DeleteConfirmDialogProps {
	client: OAuthClient;
	onConfirm: () => void;
	onCancel: () => void;
}

export function DeleteConfirmDialog( {
	client,
	onConfirm,
	onCancel,
}: DeleteConfirmDialogProps ) {
	return (
		<ConfirmDialog isOpen onConfirm={ onConfirm } onCancel={ onCancel }>
			{ sprintf(
				/* translators: %s: client name */
				__(
					'Are you sure you want to delete "%s"? This action cannot be undone.',
					'rtcamp-publish-with-ai'
				),
				client.client_name
			) }
		</ConfirmDialog>
	);
}
