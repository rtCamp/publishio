/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import { Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews';
import type { View, Action } from '@wordpress/dataviews';

/**
 * Internal dependencies
 */
import { AdminHeader } from '../shared/AdminHeader';
import type { OAuthClient, OAuthClientFormData } from './types';
import { useClients } from './useClients';
import { ClientFormModal } from './ClientFormModal';
import { DeleteConfirmDialog } from './DeleteConfirmDialog';
import { clientFields } from './clientFields';

const DEFAULT_VIEW: View = {
	type: 'table',
	page: 1,
	perPage: 10,
	sort: { field: 'client_name', direction: 'asc' },
	fields: [
		'app',
		'client_name',
		'client_id',
		'is_public',
		'scope',
		'registered_at',
	],
};

const DEFAULT_LAYOUTS = { table: {} };

export function ClientsScreen() {
	const { clients, isLoading, error, clearError, save, remove } =
		useClients();

	const [ view, setView ] = useState< View >( DEFAULT_VIEW );
	const [ editTarget, setEditTarget ] = useState< OAuthClient | null >(
		null
	);
	const [ deleteTarget, setDeleteTarget ] = useState< OAuthClient | null >(
		null
	);
	const [ isFormOpen, setIsFormOpen ] = useState( false );
	const [ newClientSecret, setNewClientSecret ] = useState< string | null >(
		null
	);

	const actions: Action< OAuthClient >[] = [
		{
			id: 'edit',
			label: __( 'Edit', 'rtcamp-publish-with-ai' ),
			callback: ( items ) => {
				setEditTarget( items[ 0 ] ?? null );
				setIsFormOpen( true );
			},
		},
		{
			id: 'delete',
			label: __( 'Delete', 'rtcamp-publish-with-ai' ),
			callback: ( items ) => {
				setDeleteTarget( items[ 0 ] ?? null );
			},
		},
	];

	async function handleSave( data: OAuthClientFormData ) {
		const result = await save( data, editTarget?.id );
		if ( 'client_secret' in result && result.client_secret ) {
			setNewClientSecret( result.client_secret );
		}
	}

	async function handleDelete() {
		if ( ! deleteTarget ) {
			return;
		}
		await remove( deleteTarget.id );
		setDeleteTarget( null );
	}

	function handleFormClose() {
		setIsFormOpen( false );
		setEditTarget( null );
	}

	const { data: processedData, paginationInfo } = filterSortAndPaginate(
		clients,
		view,
		clientFields
	);

	return (
		<>
			<AdminHeader
				title={ __( 'Clients', 'rtcamp-publish-with-ai' ) }
				description={ __(
					'Manage OAuth clients for MCP access.',
					'rtcamp-publish-with-ai'
				) }
				actions={
					<Button
						variant="primary"
						onClick={ () => setIsFormOpen( true ) }
					>
						{ __( 'Register Client', 'rtcamp-publish-with-ai' ) }
					</Button>
				}
			/>

			{ error && (
				<Notice status="error" isDismissible onRemove={ clearError }>
					{ error }
				</Notice>
			) }

			{ newClientSecret && (
				<Notice
					status="success"
					isDismissible
					onRemove={ () => setNewClientSecret( null ) }
				>
					<strong>
						{ __(
							'Client secret (shown once):',
							'rtcamp-publish-with-ai'
						) }
					</strong>{ ' ' }
					<code>{ newClientSecret }</code>
				</Notice>
			) }

			<div className="p-6">
				<DataViews
					data={ processedData }
					fields={ clientFields }
					view={ view }
					onChangeView={ setView }
					actions={ actions }
					getItemId={ ( item ) => String( item.id ) }
					isLoading={ isLoading }
					paginationInfo={ paginationInfo }
					defaultLayouts={ DEFAULT_LAYOUTS }
					empty={
						<p className="text-center text-sm text-gray-500 my-20">
							{ __(
								'No clients registered yet.',
								'rtcamp-publish-with-ai'
							) }
						</p>
					}
				/>
			</div>

			{ isFormOpen && (
				<ClientFormModal
					{ ...( editTarget ? { client: editTarget } : {} ) }
					onSave={ handleSave }
					onClose={ handleFormClose }
				/>
			) }

			{ deleteTarget && (
				<DeleteConfirmDialog
					client={ deleteTarget }
					onConfirm={ handleDelete }
					onCancel={ () => setDeleteTarget( null ) }
				/>
			) }
		</>
	);
}
