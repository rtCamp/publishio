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
import type { OAuthConnection } from '../types';
import { NameField } from '../../shared/table/fields/NameField';
import { RegisteredField } from '../../shared/table/fields/RegisteredField';
import { LastActiveField } from '../../shared/table/fields/LastActiveField';
import { UsersField } from './fields/UsersField';
import { EmptyState } from '../../shared/EmptyState';
import { DeleteConnectionDialog } from '../DeleteConnectionDialog';

const DEFAULT_VIEW: View = {
	type: 'table',
	page: 1,
	perPage: 10,
	sort: { field: 'client_name', direction: 'asc' },
	fields: [ 'client_name', 'users', 'registered_at', 'last_active_at' ],
};

const DEFAULT_LAYOUTS = { table: {} };
const DEFAULT_CONFIG = { perPageSizes: [ 10 ] };

const connectionFields: Field< OAuthConnection >[] = [
	{
		id: 'client_name',
		label: __( 'Name', 'rtcamp-publish-with-ai' ),
		enableSorting: true,
		enableGlobalSearch: true,
		getValue: ( { item } ) => item.client_name,
		render: ( { item } ) => <NameField item={ item } />,
	},
	{
		id: 'users',
		label: __( 'Users', 'rtcamp-publish-with-ai' ),
		getValue: ( { item } ) => item.users.map( ( u ) => u.name ).join( ' ' ),
		enableGlobalSearch: true,
		render: ( { item } ) => <UsersField users={ item.users } />,
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
		label: __( 'Registered', 'rtcamp-publish-with-ai' ),
		enableSorting: true,
		getValue: ( { item } ) =>
			new Date( item.registered_at * 1000 ).toISOString(),
		render: ( { item } ) => <RegisteredField item={ item } />,
	},
];

interface ConnectionsTableProps {
	connections: OAuthConnection[];
	isLoading: boolean;
	onDelete: ( connection: OAuthConnection ) => Promise< void >;
}

export function ConnectionsTable( {
	connections,
	isLoading,
	onDelete,
}: ConnectionsTableProps ) {
	const [ view, setView ] = useState< View >( DEFAULT_VIEW );

	const actions: Action< OAuthConnection >[] = [
		{
			id: 'delete',
			label: __( 'Delete', 'rtcamp-publish-with-ai' ),
			modalHeader: __( 'Delete Connection', 'rtcamp-publish-with-ai' ),
			modalSize: 'small',
			RenderModal: ( { items, closeModal } ) => (
				<DeleteConnectionDialog
					connection={ items[ 0 ]! }
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
		connections,
		view,
		connectionFields
	);

	return (
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
					<EmptyState
						message={ __(
							'No AI apps have connected yet. Claude.ai and other AI tools will appear here after a user signs in for the first time.',
							'rtcamp-publish-with-ai'
						) }
					/>
				}
			/>
		</div>
	);
}
