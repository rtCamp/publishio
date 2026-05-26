/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import type { OAuthConnection } from './types';

const REST_PATH = '/rtpwai/v1/connections';

export const connectionsApi = {
	list: (): Promise< OAuthConnection[] > => apiFetch( { path: REST_PATH } ),

	remove: ( id: number ): Promise< { tokens_deleted: number } > =>
		apiFetch( { path: `${ REST_PATH }/${ id }`, method: 'DELETE' } ),
};
