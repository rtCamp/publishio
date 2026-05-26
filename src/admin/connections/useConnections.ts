/**
 * WordPress dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type {
	OAuthConnection,
	CreatedOAuthConnection,
	OAuthConnectionFormData,
} from './types';
import { connectionsApi } from './api';

export function useConnections() {
	const [ connections, setConnections ] = useState< OAuthConnection[] >( [] );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState< string | null >( null );

	useEffect( () => {
		let cancelled = false;

		setIsLoading( true );
		setError( null );

		connectionsApi
			.list()
			.then( ( data ) => {
				if ( ! cancelled ) {
					setConnections( data );
				}
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setError(
						__(
							'Failed to load connections.',
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
		data: OAuthConnectionFormData,
		id?: number
	): Promise< CreatedOAuthConnection | OAuthConnection > {
		if ( id ) {
			const updated = await connectionsApi.update( id, data );
			setConnections( ( prev ) =>
				prev.map( ( c ) => ( c.id === updated.id ? updated : c ) )
			);
			return updated;
		}

		const created = await connectionsApi.create( data );
		setConnections( ( prev ) => [ ...prev, created ] );
		return created;
	}

	async function remove( id: number ): Promise< void > {
		await connectionsApi.remove( id );
		setConnections( ( prev ) => prev.filter( ( c ) => c.id !== id ) );
	}

	return {
		connections,
		isLoading,
		error,
		clearError: () => setError( null ),
		save,
		remove,
	};
}
