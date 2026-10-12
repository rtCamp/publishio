/**
 * WordPress dependencies
 */
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { AdminHeader } from '../shared/AdminHeader';
import { ScreenNotices } from '../shared/ScreenNotices';
import type { OAuthConnection } from './types';
import { useConnections, CONNECTIONS_NOTICES_CONTEXT } from './useConnections';
import { ConnectionsTable } from './table/ConnectionsTable';

export function ConnectionsScreen() {
	const {
		connections,
		isLoading,
		page,
		setPage,
		order,
		changeOrder,
		total,
		remove,
	} = useConnections();
	const { createSuccessNotice } = useDispatch( noticesStore );

	async function handleDelete( connection: OAuthConnection ) {
		if ( ! ( await remove( connection.id, connection.user.id ) ) ) {
			return;
		}
		createSuccessNotice( __( 'Connection removed.', 'publishio' ), {
			type: 'snackbar',
			context: CONNECTIONS_NOTICES_CONTEXT,
		} );
	}

	return (
		<>
			<AdminHeader
				title={ __( 'Connections', 'publishio' ) }
				description={ __(
					'AI apps that users have authorized to access your site.',
					'publishio'
				) }
			/>

			<ConnectionsTable
				connections={ connections }
				isLoading={ isLoading }
				page={ page }
				total={ total }
				onPageChange={ setPage }
				order={ order }
				onOrderChange={ changeOrder }
				onDelete={ handleDelete }
			/>

			<ScreenNotices context={ CONNECTIONS_NOTICES_CONTEXT } />
		</>
	);
}
