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
import type { OAuthConnection, OAuthConnectionFormData } from './types';
import { useConnections } from './useConnections';
import { ConnectionFormModal } from './ConnectionFormModal';
import { DeleteConfirmDialog } from './DeleteConfirmDialog';
import { connectionFields } from './connectionFields';

const DEFAULT_VIEW: View = {
	type: 'table',
	page: 1,
	perPage: 10,
	sort: { field: 'client_name', direction: 'asc' },
	fields: [ 'client_name', 'users', 'registered_at', 'last_active_at' ],
};

const DEFAULT_LAYOUTS = { table: {} };

const DEFAULT_CONFIG = {
	perPageSizes: [ 10 ],
};

export function ConnectionsScreen() {
	const { connections, isLoading, error, clearError, save, remove } =
		useConnections();

	const [ view, setView ] = useState< View >( DEFAULT_VIEW );
	const [ editTarget, setEditTarget ] = useState< OAuthConnection | null >(
		null
	);
	const [ deleteTarget, setDeleteTarget ] =
		useState< OAuthConnection | null >( null );
	const [ isFormOpen, setIsFormOpen ] = useState( false );
	const [ newClientSecret, setNewClientSecret ] = useState< string | null >(
		null
	);

	const actions: Action< OAuthConnection >[] = [
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

	async function handleSave( data: OAuthConnectionFormData ) {
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
		connections,
		view,
		connectionFields
	);

	return (
		<>
			<AdminHeader
				title={ __( 'Connections', 'rtcamp-publish-with-ai' ) }
				description={ __(
					'Manage OAuth connections for MCP access.',
					'rtcamp-publish-with-ai'
				) }
				actions={
					<Button
						variant="primary"
						onClick={ () => setIsFormOpen( true ) }
					>
						{ __( 'Add Connection', 'rtcamp-publish-with-ai' ) }
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
							'Connection secret (shown once):',
							'rtcamp-publish-with-ai'
						) }
					</strong>{ ' ' }
					<code>{ newClientSecret }</code>
				</Notice>
			) }

			<div className="p-6">
				<DataViews
					data={ processedData }
					fields={ connectionFields }
					view={ view }
					onChangeView={ ( next ) =>
						setView( { ...next, page: 1, perPage: 10 } )
					}
					actions={ actions }
					getItemId={ ( item ) => String( item.id ) }
					isLoading={ isLoading }
					config={ DEFAULT_CONFIG }
					paginationInfo={ paginationInfo }
					defaultLayouts={ DEFAULT_LAYOUTS }
					empty={
						<p className="text-center text-sm text-gray-500 my-20">
							{ __(
								'No connections registered yet.',
								'rtcamp-publish-with-ai'
							) }
						</p>
					}
				/>
			</div>

			{ isFormOpen && (
				<ConnectionFormModal
					{ ...( editTarget ? { connection: editTarget } : {} ) }
					onSave={ handleSave }
					onClose={ handleFormClose }
				/>
			) }

			{ deleteTarget && (
				<DeleteConfirmDialog
					connection={ deleteTarget }
					onConfirm={ handleDelete }
					onCancel={ () => setDeleteTarget( null ) }
				/>
			) }
		</>
	);
}
