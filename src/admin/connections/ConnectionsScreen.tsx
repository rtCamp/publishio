/**
 * WordPress dependencies
 */
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { AdminHeader } from '../shared/AdminHeader';
import { ScreenNotices } from '../shared/ScreenNotices';
import type { OAuthConnection } from './types';
import { useConnections, CONNECTIONS_NOTICES_CONTEXT } from './useConnections';
import { ConnectionsTable } from './table/ConnectionsTable';

export function ConnectionsScreen() {
	const { connections, isLoading, page, setPage, total, remove } =
		useConnections();
	const { createSuccessNotice } = useDispatch( noticesStore );

	async function handleDelete( connection: OAuthConnection ) {
		const name = connection.client_name;
		await remove( connection.client_id, connection.user.id );
		createSuccessNotice(
			sprintf(
				/* translators: 1: user name, 2: app name */
				__(
					'%1$s\'s access to "%2$s" has been revoked.',
					'publish-with-ai'
				),
				connection.user.name,
				name
			),
			{ type: 'snackbar', context: CONNECTIONS_NOTICES_CONTEXT }
		);
	}

	return (
		<>
			<AdminHeader
				title={ __( 'Connections', 'publish-with-ai' ) }
				description={ __(
					'AI apps that have connected to your site. These are registered automatically the first time an app signs in.',
					'publish-with-ai'
				) }
			/>

			<ConnectionsTable
				connections={ connections }
				isLoading={ isLoading }
				page={ page }
				total={ total }
				onPageChange={ setPage }
				onDelete={ handleDelete }
			/>

			<ScreenNotices context={ CONNECTIONS_NOTICES_CONTEXT } />
		</>
	);
}
