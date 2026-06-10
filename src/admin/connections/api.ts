/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import type { OAuthConnection } from './types';

const REST_PATH = '/publishio/v1/connections';

export type ConnectionPage = { items: OAuthConnection[]; total: number };

export const connectionsApi = {
	list: ( page: number ): Promise< ConnectionPage > =>
		apiFetch( { path: `${ REST_PATH }?page=${ page }` } ),

	remove: (
		clientId: string,
		userId: number
	): Promise< { tokens_deleted: number } > =>
		apiFetch( {
			path: `${ REST_PATH }/${ clientId }/users/${ userId }`,
			method: 'DELETE',
		} ),
};
