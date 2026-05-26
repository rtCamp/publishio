/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import type {
	OAuthConnection,
	CreatedOAuthConnection,
	OAuthConnectionFormData,
} from './types';

const REST_PATH = '/rtpwai/v1/connections';

export const connectionsApi = {
	list: (): Promise< OAuthConnection[] > => apiFetch( { path: REST_PATH } ),

	create: (
		data: OAuthConnectionFormData
	): Promise< CreatedOAuthConnection > =>
		apiFetch( { path: REST_PATH, method: 'POST', data } ),

	update: (
		id: number,
		data: Partial< OAuthConnectionFormData >
	): Promise< OAuthConnection > =>
		apiFetch( { path: `${ REST_PATH }/${ id }`, method: 'PUT', data } ),

	remove: ( id: number ): Promise< void > =>
		apiFetch( { path: `${ REST_PATH }/${ id }`, method: 'DELETE' } ),
};
