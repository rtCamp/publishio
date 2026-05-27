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
		const tokensDeleted = await remove( connection.id );
		createSuccessNotice(
			tokensDeleted > 0
				? sprintf(
						/* translators: 1: connection name, 2: number of sessions revoked */
						__(
							'"%1$s" deleted. %2$d active session(s) revoked.',
							'rtcamp-publish-with-ai'
						),
						name,
						tokensDeleted
				  )
				: sprintf(
						/* translators: %s: connection name */
						__( '"%s" deleted.', 'rtcamp-publish-with-ai' ),
						name
				  ),
			{ type: 'snackbar', context: CONNECTIONS_NOTICES_CONTEXT }
		);
	}

	return (
		<>
			<AdminHeader
				title={ __( 'Connections', 'rtcamp-publish-with-ai' ) }
				description={ __(
					'AI apps that have connected to your site. These are registered automatically the first time an app signs in.',
					'rtcamp-publish-with-ai'
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
