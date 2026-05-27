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
import type {
	OAuthCredential,
	CreatedOAuthCredential,
	OAuthCredentialFormData,
	UpdateCredentialPayload,
} from './types';
import { credentialsApi } from './api';
import { PAGE_SIZE } from '../constants';

export const CREDENTIALS_NOTICES_CONTEXT = 'rtpwai/credentials';

const ERROR_OPTS = {
	type: 'snackbar' as const,
	explicitDismiss: true,
	context: CREDENTIALS_NOTICES_CONTEXT,
};

export function useCredentials() {
	const [ credentials, setCredentials ] = useState< OAuthCredential[] >( [] );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ page, setPage ] = useState( 1 );
	const [ total, setTotal ] = useState( 0 );
	const [ refreshKey, setRefreshKey ] = useState( 0 );

	const { createErrorNotice } = useDispatch( noticesStore );

	useEffect( () => {
		let cancelled = false;

		setIsLoading( true );

		credentialsApi
			.list( page )
			.then( ( { items, total: count } ) => {
				if ( ! cancelled ) {
					setCredentials( items );
					setTotal( count );
				}
			} )
			.catch( () => {
				if ( ! cancelled ) {
					createErrorNotice(
						__(
							'Failed to load credentials.',
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

	async function create(
		data: OAuthCredentialFormData
	): Promise< CreatedOAuthCredential > {
		const created = await credentialsApi.create( data );
		// New items are newest-first, so go to page 1 and force a re-fetch.
		setPage( 1 );
		setRefreshKey( ( k ) => k + 1 );
		return created;
	}

	async function update(
		id: number,
		data: UpdateCredentialPayload
	): Promise< void > {
		const updated = await credentialsApi.update( id, data );
		setCredentials( ( prev ) =>
			prev.map( ( c ) => ( c.id === id ? { ...c, ...updated } : c ) )
		);
	}

	async function remove( id: number ): Promise< number > {
		const result = await credentialsApi.remove( id );
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
	}

	return {
		credentials,
		isLoading,
		page,
		setPage,
		total,
		pageSize: PAGE_SIZE,
		create,
		update,
		remove,
	};
}
