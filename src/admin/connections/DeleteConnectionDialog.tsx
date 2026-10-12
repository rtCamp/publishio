/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { Notice } from '../shared/Notice';

interface DeleteConnectionDialogProps {
	onConfirm: () => Promise< void >;
	onCancel: undefined | ( () => void );
}

export function DeleteConnectionDialog( {
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
					'Failed to remove the connection. Please try again.',
					'publishio'
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
				{ __( 'Remove this connection?', 'publishio' ) }
			</p>
			<p className="text-sm text-gray-700">
				{ __(
					'The app loses access through it right away. Other connections for the same app stay active.',
					'publishio'
				) }
			</p>
			<p className="text-sm font-medium text-red-600">
				{ __( 'This action cannot be undone.', 'publishio' ) }
			</p>

			<div className="flex justify-end gap-2 mt-6 pt-4 border-t border-gray-200">
				<Button
					variant="primary"
					isDestructive
					isBusy={ isDeleting }
					disabled={ isDeleting }
					onClick={ handleConfirm }
				>
					{ __( 'Remove', 'publishio' ) }
				</Button>
				<Button
					variant="tertiary"
					onClick={ onCancel }
					disabled={ isDeleting }
				>
					{ __( 'Cancel', 'publishio' ) }
				</Button>
			</div>
		</>
	);
}
