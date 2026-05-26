/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { DataViews, filterSortAndPaginate } from '@wordpress/dataviews';
import type { View, Action, Field } from '@wordpress/dataviews';

/**
 * Internal dependencies
 */
import type { OAuthCredential } from '../types';
import { NameField } from '../../shared/table/fields/NameField';
import { RegisteredField } from '../../shared/table/fields/RegisteredField';
import { LastActiveField } from '../../shared/table/fields/LastActiveField';
import { ClientIdField } from './fields/ClientIdField';
import { EmptyState } from '../../shared/EmptyState';
import { DeleteCredentialDialog } from '../DeleteCredentialDialog';

const DEFAULT_VIEW: View = {
	type: 'table',
	page: 1,
	perPage: 10,
	sort: { field: 'client_name', direction: 'asc' },
	fields: [ 'client_name', 'client_id', 'registered_at', 'last_active_at' ],
};

const DEFAULT_LAYOUTS = { table: {} };
const DEFAULT_CONFIG = { perPageSizes: [ 10 ] };

const credentialFields: Field< OAuthCredential >[] = [
	{
		id: 'client_name',
		label: __( 'Name', 'rtcamp-publish-with-ai' ),
		enableSorting: true,
		enableGlobalSearch: true,
		getValue: ( { item } ) => item.client_name,
		render: ( { item } ) => <NameField item={ item } />,
	},
	{
		id: 'client_id',
		label: __( 'Client ID', 'rtcamp-publish-with-ai' ),
		enableGlobalSearch: true,
		getValue: ( { item } ) => item.client_id,
		render: ( { item } ) => <ClientIdField item={ item } />,
	},
	{
		id: 'last_active_at',
		label: __( 'Last Active', 'rtcamp-publish-with-ai' ),
		enableSorting: true,
		getValue: ( { item } ) =>
			item.last_active_at
				? new Date( item.last_active_at * 1000 ).toISOString()
				: '',
		render: ( { item } ) => <LastActiveField item={ item } />,
	},
	{
		id: 'registered_at',
		label: __( 'Created', 'rtcamp-publish-with-ai' ),
		enableSorting: true,
		getValue: ( { item } ) =>
			new Date( item.registered_at * 1000 ).toISOString(),
		render: ( { item } ) => <RegisteredField item={ item } />,
	},
];

interface CredentialsTableProps {
	credentials: OAuthCredential[];
	isLoading: boolean;
	onDelete: ( credential: OAuthCredential ) => Promise< void >;
}

export function CredentialsTable( {
	credentials,
	isLoading,
	onDelete,
}: CredentialsTableProps ) {
	const [ view, setView ] = useState< View >( DEFAULT_VIEW );

	const actions: Action< OAuthCredential >[] = [
		{
			id: 'delete',
			label: __( 'Delete', 'rtcamp-publish-with-ai' ),
			modalHeader: __( 'Delete Credential', 'rtcamp-publish-with-ai' ),
			modalSize: 'small',
			RenderModal: ( { items, closeModal } ) => (
				<DeleteCredentialDialog
					credential={ items[ 0 ]! }
					onConfirm={ async () => {
						await onDelete( items[ 0 ]! );
						closeModal?.();
					} }
					onCancel={ closeModal }
				/>
			),
		},
	];

	const { data: processedData, paginationInfo } = filterSortAndPaginate(
		credentials,
		view,
		credentialFields
	);

	return (
		<div className="p-6">
			<DataViews
				data={ processedData }
				fields={ credentialFields }
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
					<EmptyState
						message={ __(
							'No credentials created yet.',
							'rtcamp-publish-with-ai'
						) }
					/>
				}
			/>
		</div>
	);
}
