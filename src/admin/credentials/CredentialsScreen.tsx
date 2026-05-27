/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { Button } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { AdminHeader } from '../shared/AdminHeader';
import { ScreenNotices } from '../shared/ScreenNotices';
import type {
	OAuthCredential,
	CreatedOAuthCredential,
	OAuthCredentialFormData,
} from './types';
import { useCredentials, CREDENTIALS_NOTICES_CONTEXT } from './useCredentials';
import { CredentialsTable } from './table/CredentialsTable';
import { CreateCredentialModal } from './CreateCredentialModal';
import { EditCredentialModal } from './EditCredentialModal';
import { CredentialCreatedDialog } from './CredentialCreatedDialog';

export function CredentialsScreen() {
	const {
		credentials,
		isLoading,
		page,
		setPage,
		total,
		create,
		update,
		remove,
	} = useCredentials();
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	const [ isCreateOpen, setIsCreateOpen ] = useState( false );
	const [ createdCredential, setCreatedCredential ] =
		useState< CreatedOAuthCredential | null >( null );
	const [ editingCredential, setEditingCredential ] =
		useState< OAuthCredential | null >( null );

	const snackbarOpts = {
		type: 'snackbar' as const,
		context: CREDENTIALS_NOTICES_CONTEXT,
	};
	const errorOpts = { ...snackbarOpts, explicitDismiss: true };

	async function handleCreate( data: OAuthCredentialFormData ) {
		try {
			const created = await create( data );
			setIsCreateOpen( false );
			setCreatedCredential( created );
		} catch {
			createErrorNotice(
				__(
					'Failed to create credential. Please try again.',
					'rtcamp-publish-with-ai'
				),
				errorOpts
			);
		}
	}

	async function handleEdit(
		credential: OAuthCredential,
		clientName: string
	) {
		await update( credential.id, { client_name: clientName } );
		setEditingCredential( null );
		createSuccessNotice(
			sprintf(
				/* translators: %s: credential name */
				__( '"%s" updated.', 'rtcamp-publish-with-ai' ),
				clientName
			),
			snackbarOpts
		);
	}

	async function handleDelete( credential: OAuthCredential ) {
		const name = credential.client_name;
		const tokensDeleted = await remove( credential.id );
		createSuccessNotice(
			tokensDeleted > 0
				? sprintf(
						/* translators: 1: credential name, 2: number of sessions revoked */
						__(
							'"%1$s" deleted. %2$d active session(s) revoked.',
							'rtcamp-publish-with-ai'
						),
						name,
						tokensDeleted
				  )
				: sprintf(
						/* translators: %s: credential name */
						__( '"%s" deleted.', 'rtcamp-publish-with-ai' ),
						name
				  ),
			snackbarOpts
		);
	}

	return (
		<>
			<AdminHeader
				title={ __( 'Credentials', 'rtcamp-publish-with-ai' ) }
				description={ __(
					'Create and manage access credentials for custom integrations.',
					'rtcamp-publish-with-ai'
				) }
				actions={
					<Button
						variant="primary"
						onClick={ () => setIsCreateOpen( true ) }
					>
						{ __( 'Add Credential', 'rtcamp-publish-with-ai' ) }
					</Button>
				}
			/>

			<CredentialsTable
				credentials={ credentials }
				isLoading={ isLoading }
				page={ page }
				total={ total }
				onPageChange={ setPage }
				onEdit={ setEditingCredential }
				onDelete={ handleDelete }
			/>

			{ isCreateOpen && (
				<CreateCredentialModal
					onSave={ handleCreate }
					onClose={ () => setIsCreateOpen( false ) }
				/>
			) }

			{ editingCredential && (
				<EditCredentialModal
					credential={ editingCredential }
					onSave={ ( data ) =>
						handleEdit( editingCredential, data.client_name )
					}
					onClose={ () => setEditingCredential( null ) }
				/>
			) }

			{ createdCredential && (
				<CredentialCreatedDialog
					credential={ createdCredential }
					onClose={ () => setCreatedCredential( null ) }
				/>
			) }

			<ScreenNotices context={ CREDENTIALS_NOTICES_CONTEXT } />
		</>
	);
}
