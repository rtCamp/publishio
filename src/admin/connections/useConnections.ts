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
import type { OAuthConnection } from './types';
import { connectionsApi } from './api';
import { PAGE_SIZE } from '../constants';

export const CONNECTIONS_NOTICES_CONTEXT = 'rtpwai/connections';

const ERROR_OPTS = {
	type: 'snackbar' as const,
	explicitDismiss: true,
	context: CONNECTIONS_NOTICES_CONTEXT,
};

export function useConnections() {
	const [ connections, setConnections ] = useState< OAuthConnection[] >( [] );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ page, setPage ] = useState( 1 );
	const [ total, setTotal ] = useState( 0 );
	const [ refreshKey, setRefreshKey ] = useState( 0 );

	const { createErrorNotice } = useDispatch( noticesStore );

	useEffect( () => {
		let cancelled = false;

		setIsLoading( true );

		connectionsApi
			.list( page )
			.then( ( { items, total: count } ) => {
				if ( ! cancelled ) {
					setConnections( items );
					setTotal( count );
				}
			} )
			.catch( () => {
				if ( ! cancelled ) {
					createErrorNotice(
						__(
							'Failed to load connections.',
							'rtcamp-publish-with-ai'
						),
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
	}, [ page, refreshKey ] ); // eslint-disable-line react-hooks/exhaustive-deps

	async function remove( id: number ): Promise< number > {
		try {
			const result = await connectionsApi.remove( id );
			const newTotal = total - 1;
			const maxPage = Math.max( 1, Math.ceil( newTotal / PAGE_SIZE ) );
			const targetPage = Math.min( page, maxPage );
			if ( targetPage !== page ) {
				setPage( targetPage );
			} else {
				setRefreshKey( ( k ) => k + 1 );
			}
			setTotal( newTotal );
			return result.tokens_deleted;
		} catch {
			createErrorNotice(
				__(
					'Failed to delete connection. Please try again.',
					'rtcamp-publish-with-ai'
				),
				ERROR_OPTS
			);
			return 0;
		}
	}

	return {
		connections,
		isLoading,
		page,
		setPage,
		total,
		pageSize: PAGE_SIZE,
		remove,
	};
}
