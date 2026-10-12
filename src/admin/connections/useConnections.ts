/**
 * WordPress dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { ConnectionOrder, OAuthConnection } from './types';
import { connectionsApi } from './api';
import { PAGE_SIZE } from '../constants';

export const CONNECTIONS_NOTICES_CONTEXT = 'publishio/connections';

const ERROR_OPTS = {
	type: 'snackbar' as const,
	explicitDismiss: true,
	context: CONNECTIONS_NOTICES_CONTEXT,
};

export function useConnections() {
	const [ connections, setConnections ] = useState< OAuthConnection[] >( [] );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ page, setPage ] = useState( 1 );
	const [ order, setOrder ] = useState< ConnectionOrder >( 'desc' );
	const [ total, setTotal ] = useState( 0 );
	const [ refreshKey, setRefreshKey ] = useState( 0 );

	const { createErrorNotice } = useDispatch( noticesStore );

	useEffect( () => {
		let cancelled = false;

		setIsLoading( true );

		connectionsApi
			.list( page, order )
			.then( ( { items, total: count } ) => {
				if ( ! cancelled ) {
					setConnections( items );
					setTotal( count );
				}
			} )
			.catch( () => {
				if ( ! cancelled ) {
					createErrorNotice(
						__( 'Failed to load connections.', 'publishio' ),
						ERROR_OPTS
					);
				}
			} )
			.finally( () => {
				if ( ! cancelled ) {
					setIsLoading( false );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ page, order, refreshKey ] ); // eslint-disable-line react-hooks/exhaustive-deps

	function changeOrder( next: ConnectionOrder ) {
		setOrder( next );
		setPage( 1 );
	}

	/**
	 * Remove a connection. Resolves false, and reloads the list, when the connection no longer exists: already removed, or the app refreshed its token since the list loaded (which gives it a new id).
	 *
	 * @param id     Connection id.
	 * @param userId Id of the user the connection belongs to.
	 */
	async function remove( id: number, userId: number ): Promise< boolean > {
		try {
			await connectionsApi.remove( id, userId );
		} catch ( error ) {
			const status = ( error as { data?: { status?: number } } )?.data
				?.status;
			if ( status !== 404 ) {
				throw error;
			}
			setRefreshKey( ( k ) => k + 1 );
			createErrorNotice(
				__(
					'This connection changed since the page loaded, so nothing was removed. The list has been refreshed.',
					'publishio'
				),
				ERROR_OPTS
			);
			return false;
		}

		const newTotal = total - 1;
		const maxPage = Math.max( 1, Math.ceil( newTotal / PAGE_SIZE ) );
		const targetPage = Math.min( page, maxPage );
		if ( targetPage !== page ) {
			setPage( targetPage );
		} else {
			setRefreshKey( ( k ) => k + 1 );
		}
		setTotal( newTotal );
		return true;
	}

	return {
		connections,
		isLoading,
		page,
		setPage,
		order,
		changeOrder,
		total,
		pageSize: PAGE_SIZE,
		remove,
	};
}
