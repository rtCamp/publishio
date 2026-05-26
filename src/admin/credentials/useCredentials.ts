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

export const CREDENTIALS_NOTICES_CONTEXT = 'rtpwai/credentials';

const ERROR_OPTS = {
	type: 'snackbar' as const,
	explicitDismiss: true,
	context: CREDENTIALS_NOTICES_CONTEXT,
};

export function useCredentials() {
	const [ credentials, setCredentials ] = useState< OAuthCredential[] >( [] );
	const [ isLoading, setIsLoading ] = useState( true );

	const { createErrorNotice } = useDispatch( noticesStore );

	useEffect( () => {
		let cancelled = false;

		setIsLoading( true );

		credentialsApi
			.list()
			.then( ( data ) => {
				if ( ! cancelled ) {
					setCredentials( data );
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
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	async function create(
		data: OAuthCredentialFormData
	): Promise< CreatedOAuthCredential > {
		const created = await credentialsApi.create( data );
		setCredentials( ( prev ) => [ ...prev, created ] );
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
		try {
			const result = await credentialsApi.remove( id );
			setCredentials( ( prev ) => prev.filter( ( c ) => c.id !== id ) );
			return result.tokens_deleted;
		} catch {
			createErrorNotice(
				__(
					'Failed to delete credential. Please try again.',
					'rtcamp-publish-with-ai'
				),
				ERROR_OPTS
			);
			return 0;
		}
	}

	return { credentials, isLoading, create, update, remove };
}
