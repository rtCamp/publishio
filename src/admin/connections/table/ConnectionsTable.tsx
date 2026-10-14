/**
 * WordPress dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { DataViews } from '@wordpress/dataviews';
import type { View, Action, Field } from '@wordpress/dataviews';

/**
 * Internal dependencies
 */
import type { ConnectionOrder, OAuthConnection } from '../types';
import { NameField } from '../../shared/table/fields/NameField';
import { ConnectedField } from '../../shared/table/fields/ConnectedField';
import { LastActiveField } from '../../shared/table/fields/LastActiveField';
import { UserField } from './fields/UsersField';
import { EmptyState } from '../../shared/EmptyState';
import { DeleteConnectionDialog } from '../DeleteConnectionDialog';
import { PAGE_SIZE } from '../../constants';

const DEFAULT_VIEW: View = {
	type: 'table',
	page: 1,
	perPage: PAGE_SIZE,
	fields: [ 'client_name', 'user', 'connected_at', 'last_active_at' ],
};

const DEFAULT_LAYOUTS = { table: {} };
const DEFAULT_CONFIG = { perPageSizes: [ PAGE_SIZE ] };

const connectionFields: Field< OAuthConnection >[] = [
	{
		id: 'client_name',
		label: __( 'Name', 'publishio' ),
		enableSorting: false,
		getValue: ( { item } ) => item.client_name,
		render: ( { item } ) => <NameField item={ item } />,
	},
	{
		id: 'user',
		label: __( 'User', 'publishio' ),
		enableSorting: false,
		getValue: ( { item } ) => `${ item.user.name } ${ item.user.email }`,
		render: ( { item } ) => <UserField user={ item.user } />,
	},
	{
		id: 'last_active_at',
		label: __( 'Last Active', 'publishio' ),
		enableSorting: true,
		getValue: ( { item } ) =>
			new Date( item.last_active_at * 1000 ).toISOString(),
		render: ( { item } ) => <LastActiveField item={ item } />,
	},
	{
		id: 'connected_at',
		label: __( 'Connected', 'publishio' ),
		enableSorting: false,
		getValue: ( { item } ) =>
			new Date( item.connected_at * 1000 ).toISOString(),
		render: ( { item } ) => <ConnectedField item={ item } />,
	},
];

interface ConnectionsTableProps {
	connections: OAuthConnection[];
	isLoading: boolean;
	page: number;
	total: number;
	onPageChange: ( page: number ) => void;
	order: ConnectionOrder;
	onOrderChange: ( order: ConnectionOrder ) => void;
	onDelete: ( connection: OAuthConnection ) => Promise< void >;
}

export function ConnectionsTable( {
	connections,
	isLoading,
	page,
	total,
	onPageChange,
	order,
	onOrderChange,
	onDelete,
}: ConnectionsTableProps ) {
	const [ view, setView ] = useState< View >( {
		...DEFAULT_VIEW,
		page,
		sort: { field: 'last_active_at', direction: order },
	} );

	// Sync external page changes (e.g. after a removal navigates back a page).
	useEffect( () => {
		setView( ( prev ) => ( { ...prev, page } ) );
	}, [ page ] );

	const actions: Action< OAuthConnection >[] = [
		{
			id: 'remove',
			label: __( 'Remove', 'publishio' ),
			modalHeader: __( 'Remove Connection', 'publishio' ),
			modalSize: 'small',
			RenderModal: ( { items, closeModal } ) => (
				<DeleteConnectionDialog
					onConfirm={ async () => {
						// DataViews always passes the selected row.
						await onDelete( items[ 0 ]! );
						closeModal?.();
					} }
					onCancel={ closeModal }
				/>
			),
		},
	];

	const paginationInfo = {
		totalItems: total,
		totalPages: Math.ceil( total / PAGE_SIZE ),
	};

	return (
		<div className="p-6">
			<DataViews
				data={ connections }
				fields={ connectionFields }
				view={ view }
				onChangeView={ ( next ) => {
					const nextOrder = next.sort?.direction ?? 'desc';
					setView( {
						...next,
						perPage: PAGE_SIZE,
						sort: { field: 'last_active_at', direction: nextOrder },
					} );
					if ( nextOrder !== order ) {
						onOrderChange( nextOrder );
					} else if ( ( next.page ?? 1 ) !== page ) {
						onPageChange( next.page ?? 1 );
					}
				} }
				search={ false }
				actions={ actions }
				getItemId={ ( item ) => String( item.id ) }
				isLoading={ isLoading }
				config={ DEFAULT_CONFIG }
				paginationInfo={ paginationInfo }
				defaultLayouts={ DEFAULT_LAYOUTS }
				empty={
					<EmptyState
						message={ __(
							'No AI apps have connected yet. Connections will appear here after a user signs in for the first time.',
							'publishio'
						) }
						action={
							<Button
								variant="link"
								href={ window.publishioAdmin?.statusUrl ?? '' }
							>
								{ __(
									"Can't connect an app? Check your site's status",
									'publishio'
								) }
							</Button>
						}
					/>
				}
			/>
		</div>
	);
}
