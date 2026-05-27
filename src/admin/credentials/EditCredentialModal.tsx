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
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { OAuthCredential, UpdateCredentialPayload } from './types';
import { isValidHttpsUrl, getApiErrorMessage } from './utils';
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
	const [ clientUri, setClientUri ] = useState( credential.client_uri ?? '' );
	const [ logoUri, setLogoUri ] = useState( credential.logo_uri ?? '' );
	const [ tosUri, setTosUri ] = useState( credential.tos_uri ?? '' );
	const [ policyUri, setPolicyUri ] = useState( credential.policy_uri ?? '' );
	const [ contacts, setContacts ] = useState(
		( credential.contacts ?? [] ).join( ', ' )
	);
	const [ softwareId, setSoftwareId ] = useState(
		credential.software_id ?? ''
	);
	const [ softwareVersion, setSoftwareVersion ] = useState(
		credential.software_version ?? ''
	);
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

		const urlFields: Array< { label: string; value: string } > = [
			{
				label: __( 'Website URL', 'rtcamp-publish-with-ai' ),
				value: clientUri,
			},
			{
				label: __( 'Logo URL', 'rtcamp-publish-with-ai' ),
				value: logoUri,
			},
			{
				label: __( 'Terms of Service URL', 'rtcamp-publish-with-ai' ),
				value: tosUri,
			},
			{
				label: __( 'Privacy Policy URL', 'rtcamp-publish-with-ai' ),
				value: policyUri,
			},
		];

		const invalidFields = urlFields
			.filter( ( f ) => ! isValidHttpsUrl( f.value ) )
			.map( ( f ) => f.label );

		if ( invalidFields.length > 0 ) {
			setError(
				sprintf(
					/* translators: %s: comma-separated list of field names */
					__(
						'Invalid URL in: %s. URLs must use https://.',
						'rtcamp-publish-with-ai'
					),
					invalidFields.join( ', ' )
				)
			);
			return;
		}

		setIsSaving( true );
		setError( null );

		try {
			await onSave( {
				client_name: clientName.trim(),
				client_uri: clientUri.trim() || null,
				logo_uri: logoUri.trim() || null,
				tos_uri: tosUri.trim() || null,
				policy_uri: policyUri.trim() || null,
				contacts: contacts.trim()
					? contacts
							.split( ',' )
							.map( ( s ) => s.trim() )
							.filter( Boolean )
					: null,
				software_id: softwareId.trim() || null,
				software_version: softwareVersion.trim() || null,
			} );
		} catch ( err ) {
			setError(
				getApiErrorMessage( err ) ??
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
				<Notice
					status="error"
					className="mb-4 sticky top-0 z-10 shadow"
				>
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

				<details
					className="border border-gray-200 rounded-md"
					open={
						!! (
							credential.client_uri ||
							credential.logo_uri ||
							credential.tos_uri ||
							credential.policy_uri ||
							credential.contacts?.length ||
							credential.software_id ||
							credential.software_version
						)
					}
				>
					<summary className="px-3 py-2 cursor-pointer text-sm font-medium text-gray-600 select-none">
						{ __( 'Advanced', 'rtcamp-publish-with-ai' ) }
					</summary>
					<div className="flex flex-col gap-4 px-3 pb-3 pt-2">
						<TextControl
							__next40pxDefaultSize
							label={ __(
								'Website URL',
								'rtcamp-publish-with-ai'
							) }
							help={ __(
								'Homepage of the application.',
								'rtcamp-publish-with-ai'
							) }
							value={ clientUri }
							onChange={ setClientUri }
							placeholder="https://example.com"
							type="url"
						/>
						<div>
							<TextControl
								__next40pxDefaultSize
								label={ __(
									'Logo URL',
									'rtcamp-publish-with-ai'
								) }
								help={ __(
									'Shown on the consent screen.',
									'rtcamp-publish-with-ai'
								) }
								value={ logoUri }
								onChange={ setLogoUri }
								placeholder="https://example.com/logo.png"
								type="url"
							/>
							{ logoUri && (
								<img
									src={ logoUri }
									alt={ __(
										'Logo preview',
										'rtcamp-publish-with-ai'
									) }
									className="mt-2 h-12 max-w-[120px] w-auto rounded object-contain"
								/>
							) }
						</div>
						<TextControl
							__next40pxDefaultSize
							label={ __(
								'Terms of Service URL',
								'rtcamp-publish-with-ai'
							) }
							value={ tosUri }
							onChange={ setTosUri }
							placeholder="https://example.com/terms"
							type="url"
						/>
						<TextControl
							__next40pxDefaultSize
							label={ __(
								'Privacy Policy URL',
								'rtcamp-publish-with-ai'
							) }
							value={ policyUri }
							onChange={ setPolicyUri }
							placeholder="https://example.com/privacy"
							type="url"
						/>
						<TextControl
							__next40pxDefaultSize
							label={ __(
								'Contact Email(s)',
								'rtcamp-publish-with-ai'
							) }
							help={ __(
								'Comma-separated email addresses.',
								'rtcamp-publish-with-ai'
							) }
							value={ contacts }
							onChange={ setContacts }
							placeholder="admin@example.com"
						/>
						<TextControl
							__next40pxDefaultSize
							label={ __(
								'Software ID',
								'rtcamp-publish-with-ai'
							) }
							help={ __(
								'Unique identifier for the software (e.g. UUID).',
								'rtcamp-publish-with-ai'
							) }
							value={ softwareId }
							onChange={ setSoftwareId }
						/>
						<TextControl
							__next40pxDefaultSize
							label={ __(
								'Software Version',
								'rtcamp-publish-with-ai'
							) }
							value={ softwareVersion }
							onChange={ setSoftwareVersion }
							placeholder="1.0.0"
						/>
					</div>
				</details>
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
