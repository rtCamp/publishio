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
import type { OAuthCredentialFormData } from './types';
import { Notice } from '../shared/Notice';

interface CreateCredentialModalProps {
	onSave: ( data: OAuthCredentialFormData ) => Promise< void >;
	onClose: () => void;
}

function textToUris( text: string ): string[] {
	return text
		.split( '\n' )
		.map( ( s ) => s.trim() )
		.filter( Boolean );
}

export function CreateCredentialModal( {
	onSave,
	onClose,
}: CreateCredentialModalProps ) {
	const [ clientName, setClientName ] = useState( '' );
	const [ redirectUrisText, setRedirectUrisText ] = useState( '' );
	const [ isSaving, setIsSaving ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );

	async function handleSave() {
		if ( ! clientName.trim() ) {
			setError(
				__( 'Client name is required.', 'rtcamp-publish-with-ai' )
			);
			return;
		}

		const uris = textToUris( redirectUrisText );

		if ( uris.length === 0 ) {
			setError(
				__(
					'At least one redirect URL is required.',
					'rtcamp-publish-with-ai'
				)
			);
			return;
		}

		setIsSaving( true );
		setError( null );

		try {
			await onSave( {
				client_name: clientName.trim(),
				redirect_uris: uris,
			} );
		} catch {
			setError(
				__(
					'Failed to create credential. Please try again.',
					'rtcamp-publish-with-ai'
				)
			);
		} finally {
			setIsSaving( false );
		}
	}

	return (
		<Modal
			title={ __( 'Add New Credential', 'rtcamp-publish-with-ai' ) }
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
					placeholder={ __(
						'e.g. My Integration',
						'rtcamp-publish-with-ai'
					) }
				/>

				<TextareaControl
					label={ __( 'Redirect URL(s)', 'rtcamp-publish-with-ai' ) }
					help={ __( 'One URL per line.', 'rtcamp-publish-with-ai' ) }
					value={ redirectUrisText }
					onChange={ setRedirectUrisText }
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
					{ __( 'Create Credential', 'rtcamp-publish-with-ai' ) }
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
