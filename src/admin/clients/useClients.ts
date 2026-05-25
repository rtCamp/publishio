/**
 * WordPress dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type {
	OAuthClient,
	CreatedOAuthClient,
	OAuthClientFormData,
} from './types';
import { clientsApi } from './api';

export function useClients() {
	const [ clients, setClients ] = useState< OAuthClient[] >( [] );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState< string | null >( null );

	useEffect( () => {
		let cancelled = false;

		setIsLoading( true );
		setError( null );

		clientsApi
			.list()
			.then( ( data ) => {
				if ( ! cancelled ) {
					setClients( data );
				}
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setError(
						__(
							'Failed to load clients.',
							'rtcamp-publish-with-ai'
						)
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
	}, [] );

	async function save(
		data: OAuthClientFormData,
		id?: number
	): Promise< CreatedOAuthClient | OAuthClient > {
		if ( id ) {
			const updated = await clientsApi.update( id, data );
			setClients( ( prev ) =>
				prev.map( ( c ) => ( c.id === updated.id ? updated : c ) )
			);
			return updated;
		}

		const created = await clientsApi.create( data );
		setClients( ( prev ) => [ ...prev, created ] );
		return created;
	}

	async function remove( id: number ): Promise< void > {
		await clientsApi.remove( id );
		setClients( ( prev ) => prev.filter( ( c ) => c.id !== id ) );
	}

	return {
		clients,
		isLoading,
		error,
		clearError: () => setError( null ),
		save,
		remove,
	};
}
