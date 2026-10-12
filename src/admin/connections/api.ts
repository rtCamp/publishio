/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import type { ConnectionOrder, OAuthConnection } from './types';

const REST_PATH = '/publishio/v1/connections';

export type ConnectionPage = { items: OAuthConnection[]; total: number };

export const connectionsApi = {
	list: ( page: number, order: ConnectionOrder ): Promise< ConnectionPage > =>
		apiFetch( { path: `${ REST_PATH }?page=${ page }&order=${ order }` } ),

	remove: ( id: number, userId: number ): Promise< { deleted: boolean } > =>
		apiFetch( {
			path: `${ REST_PATH }/${ id }?user_id=${ userId }`,
			method: 'DELETE',
		} ),
};
