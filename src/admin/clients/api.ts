/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import type {
	OAuthClient,
	CreatedOAuthClient,
	OAuthClientFormData,
} from './types';

const REST_PATH = '/rtpwai/v1/clients';

export const clientsApi = {
	list: (): Promise< OAuthClient[] > => apiFetch( { path: REST_PATH } ),

	create: ( data: OAuthClientFormData ): Promise< CreatedOAuthClient > =>
		apiFetch( { path: REST_PATH, method: 'POST', data } ),

	update: (
		id: number,
		data: Partial< OAuthClientFormData >
	): Promise< OAuthClient > =>
		apiFetch( { path: `${ REST_PATH }/${ id }`, method: 'PUT', data } ),

	remove: ( id: number ): Promise< void > =>
		apiFetch( { path: `${ REST_PATH }/${ id }`, method: 'DELETE' } ),
};
