/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import {
	Modal,
	Button,
	TextControl,
	TextareaControl,
	SelectControl,
	CheckboxControl,
	ToggleControl,
	Notice,
	Flex,
} from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { OAuthConnection, OAuthConnectionFormData } from './types';

interface ConnectionFormModalProps {
	connection?: OAuthConnection;
	onSave: ( data: OAuthConnectionFormData ) => Promise< void >;
	onClose: () => void;
}

const SCOPE_OPTIONS = [ 'mcp:read', 'mcp:write' ] as const;

function urisToText( uris: string[] ): string {
	return uris.join( '\n' );
}

function textToUris( text: string ): string[] {
	return text
		.split( '\n' )
		.map( ( s ) => s.trim() )
		.filter( Boolean );
}

export function ConnectionFormModal( {
	connection,
	onSave,
	onClose,
}: ConnectionFormModalProps ) {
	const isEditing = !! connection;

	const [ clientName, setClientName ] = useState(
		connection?.client_name ?? ''
	);
	const [ redirectUrisText, setRedirectUrisText ] = useState(
		urisToText( connection?.redirect_uris ?? [] )
	);
	const [ isPublic, setIsPublic ] = useState( connection?.is_public ?? true );
	const [ scopeRead, setScopeRead ] = useState(
		connection ? connection.scope.includes( 'mcp:read' ) : true
	);
	const [ scopeWrite, setScopeWrite ] = useState(
		connection ? connection.scope.includes( 'mcp:write' ) : true
	);
	const [ includeRefreshToken, setIncludeRefreshToken ] = useState(
		connection ? connection.grant_types.includes( 'refresh_token' ) : false
	);
	const [ isSaving, setIsSaving ] = useState( false );
	const [ error, setError ] = useState< string | null >( null );

	const title = isEditing
		? sprintf(
				/* translators: %s: connection name */
				__( 'Edit Connection: %s', 'rtcamp-publish-with-ai' ),
				connection.client_name
		  )
		: __( 'Add New Connection', 'rtcamp-publish-with-ai' );

	async function handleSave() {
		if ( ! clientName.trim() ) {
			setError(
				__( 'Connection name is required.', 'rtcamp-publish-with-ai' )
			);
			return;
		}

		const uris = textToUris( redirectUrisText );

		if ( uris.length === 0 ) {
			setError(
				__(
					'At least one redirect URI is required.',
					'rtcamp-publish-with-ai'
				)
			);
			return;
		}

		const scope = SCOPE_OPTIONS.filter(
			( s ) =>
				( s === 'mcp:read' && scopeRead ) ||
				( s === 'mcp:write' && scopeWrite )
		).join( ' ' );

		const grantTypes = [
			'authorization_code',
			...( includeRefreshToken ? [ 'refresh_token' ] : [] ),
		];

		setIsSaving( true );
		setError( null );

		try {
			await onSave( {
				client_name: clientName.trim(),
				redirect_uris: uris,
				is_public: isPublic,
				scope,
				grant_types: grantTypes,
			} );
			onClose();
		} catch {
			setError(
				__(
					'Failed to save connection. Please try again.',
					'rtcamp-publish-with-ai'
				)
			);
		} finally {
			setIsSaving( false );
		}
	}

	return (
		<Modal title={ title } onRequestClose={ onClose } size="medium">
			{ error && (
				<Notice
					status="error"
					isDismissible={ false }
					className="sticky top-0"
				>
					{ error }
				</Notice>
			) }

			<div className="flex flex-col gap-4">
				<TextControl
					label={ __( 'Connection Name', 'rtcamp-publish-with-ai' ) }
					value={ clientName }
					onChange={ setClientName }
				/>

				<TextareaControl
					label={ __( 'Redirect URIs', 'rtcamp-publish-with-ai' ) }
					help={ __( 'One URI per line.', 'rtcamp-publish-with-ai' ) }
					value={ redirectUrisText }
					onChange={ setRedirectUrisText }
					rows={ 3 }
				/>

				<SelectControl
					__next40pxDefaultSize
					label={ __( 'Connection Type', 'rtcamp-publish-with-ai' ) }
					value={ isPublic ? 'public' : 'confidential' }
					options={ [
						{
							label: __(
								'Public (PKCE only)',
								'rtcamp-publish-with-ai'
							),
							value: 'public',
						},
						{
							label: __(
								'Confidential (Client Secret)',
								'rtcamp-publish-with-ai'
							),
							value: 'confidential',
						},
					] }
					onChange={ ( v ) => setIsPublic( v === 'public' ) }
					disabled={ isEditing }
					help={
						isEditing
							? __(
									'Connection type cannot be changed after creation.',
									'rtcamp-publish-with-ai'
							  )
							: undefined
					}
				/>

				<fieldset className="border-0 m-0 p-0">
					<legend className="mb-2 text-sm font-medium text-gray-900">
						{ __( 'Scope', 'rtcamp-publish-with-ai' ) }
					</legend>
					<Flex direction="column" gap={ 2 }>
						<CheckboxControl
							label="mcp:read"
							checked={ scopeRead }
							onChange={ setScopeRead }
						/>
						<CheckboxControl
							label="mcp:write"
							checked={ scopeWrite }
							onChange={ setScopeWrite }
						/>
					</Flex>
				</fieldset>

				<ToggleControl
					label={ __(
						'Include refresh token',
						'rtcamp-publish-with-ai'
					) }
					checked={ includeRefreshToken }
					onChange={ setIncludeRefreshToken }
				/>
			</div>

			<div className="flex justify-end gap-2 mt-6 pt-4 border-t border-gray-200">
				<Button
					variant="primary"
					onClick={ handleSave }
					isBusy={ isSaving }
					disabled={ isSaving }
				>
					{ isEditing
						? __( 'Update', 'rtcamp-publish-with-ai' )
						: __( 'Add', 'rtcamp-publish-with-ai' ) }
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
