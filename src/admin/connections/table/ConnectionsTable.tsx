/**
 * WordPress dependencies
 */
import { useState, useEffect } from '@wordpress/element';
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
import { UserField } from './fields/UsersField';
import { EmptyState } from '../../shared/EmptyState';
import { DeleteConnectionDialog } from '../DeleteConnectionDialog';
import { PAGE_SIZE } from '../../constants';

const DEFAULT_VIEW: View = {
	type: 'table',
	page: 1,
	perPage: PAGE_SIZE,
	sort: { field: 'client_name', direction: 'asc' },
	fields: [ 'client_name', 'user', 'registered_at', 'last_active_at' ],
};

const DEFAULT_LAYOUTS = { table: {} };
const DEFAULT_CONFIG = { perPageSizes: [ PAGE_SIZE ] };

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
		id: 'user',
		label: __( 'User', 'rtcamp-publish-with-ai' ),
		getValue: ( { item } ) => `${ item.user.name } ${ item.user.email }`,
		enableGlobalSearch: true,
		render: ( { item } ) => <UserField user={ item.user } />,
	},
	{
		id: 'last_active_at',
		label: __( 'Last Active', 'rtcamp-publish-with-ai' ),
		enableSorting: true,
		getValue: ( { item } ) =>
			new Date( item.last_active_at * 1000 ).toISOString(),
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
	page: number;
	total: number;
	onPageChange: ( page: number ) => void;
	onDelete: ( connection: OAuthConnection ) => Promise< void >;
}

export function ConnectionsTable( {
	connections,
	isLoading,
	page,
	total,
	onPageChange,
	onDelete,
}: ConnectionsTableProps ) {
	const [ view, setView ] = useState< View >( { ...DEFAULT_VIEW, page } );

	// Sync external page changes (e.g. after delete navigates back a page).
	useEffect( () => {
		setView( ( prev ) => ( { ...prev, page } ) );
	}, [ page ] );

	const actions: Action< OAuthConnection >[] = [
		{
			id: 'delete',
			label: __( 'Delete', 'rtcamp-publish-with-ai' ),
			modalHeader: __( 'Delete Connection', 'rtcamp-publish-with-ai' ),
			modalSize: 'small',
			RenderModal: ( { items, closeModal } ) => (
				<DeleteConnectionDialog
					connection={
						items[ 0 ]! /* DataViews always passes the selected row */
					}
					onConfirm={ async () => {
						await onDelete( items[ 0 ]! );
						closeModal?.();
					} }
					onCancel={ closeModal }
				/>
			),
		},
	];

	// Sort/search within the current page; disable re-pagination (server owns that).
	const { data: processedData } = filterSortAndPaginate(
		connections,
		{ ...view, page: 1, perPage: connections.length || 1 },
		connectionFields
	);

	const paginationInfo = {
		totalItems: total,
		totalPages: Math.ceil( total / PAGE_SIZE ),
	};

	return (
		<div className="p-6">
			<DataViews
				data={ processedData }
				fields={ connectionFields }
				view={ view }
				onChangeView={ ( next ) => {
					const nextPage = next.page ?? 1;
					setView( { ...next, perPage: PAGE_SIZE } );
					if ( nextPage !== page ) {
						onPageChange( nextPage );
					}
				} }
				actions={ actions }
				getItemId={ ( item ) =>
					`${ item.client_id }:${ item.user.id }`
				}
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
