/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import {
	Modal,
	Button,
	TextControl,
	TextareaControl,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { OAuthCredential, UpdateCredentialPayload } from './types';
import { Notice } from '../shared/Notice';

interface EditCredentialModalProps {
	credential: OAuthCredential;
	onSave: ( data: UpdateCredentialPayload ) => Promise< void >;
	onClose: () => void;
}

export function EditCredentialModal( {
	credential,
	onSave,
	onClose,
}: EditCredentialModalProps ) {
	const [ clientName, setClientName ] = useState( credential.client_name );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );

	const redirectUrisText = credential.redirect_uris.join( '\n' );

	async function handleSave() {
		if ( ! clientName.trim() ) {
			setError(
				__( 'Client name is required.', 'rtcamp-publish-with-ai' )
			);
			return;
		}

		setIsSaving( true );
		setError( null );

		try {
			await onSave( { client_name: clientName.trim() } );
		} catch {
			setError(
				__(
					'Failed to update credential. Please try again.',
					'rtcamp-publish-with-ai'
				)
			);
		} finally {
			setIsSaving( false );
		}
	}

	return (
		<Modal
			title={ __( 'Edit Credential', 'rtcamp-publish-with-ai' ) }
			onRequestClose={ onClose }
			size="medium"
		>
			{ error && (
				<Notice status="error" className="mb-4">
					{ error }
				</Notice>
			) }

			<div className="flex flex-col gap-4">
				<TextControl
					__next40pxDefaultSize
					label={ __( 'Client Name', 'rtcamp-publish-with-ai' ) }
					value={ clientName }
					onChange={ setClientName }
				/>

				<TextareaControl
					label={ __( 'Redirect URL(s)', 'rtcamp-publish-with-ai' ) }
					help={ __(
						'Redirect URLs cannot be changed after creation.',
						'rtcamp-publish-with-ai'
					) }
					value={ redirectUrisText }
					onChange={ () => {} }
					disabled
					rows={ 3 }
				/>
			</div>

			<div className="flex justify-end gap-2 mt-6 pt-4 border-t border-gray-200">
				<Button
					variant="primary"
					onClick={ handleSave }
					isBusy={ isSaving }
					disabled={ isSaving }
				>
					{ __( 'Save Changes', 'rtcamp-publish-with-ai' ) }
				</Button>
				<Button
					variant="tertiary"
					onClick={ onClose }
					disabled={ isSaving }
				>
					{ __( 'Cancel', 'rtcamp-publish-with-ai' ) }
				</Button>
			</div>
		</Modal>
	);
}
