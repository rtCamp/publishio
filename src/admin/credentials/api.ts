/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import type {
	OAuthCredential,
	CreatedOAuthCredential,
	OAuthCredentialFormData,
	UpdateCredentialPayload,
} from './types';

const REST_PATH = '/rtpwai/v1/credentials';

export type CredentialPage = { items: OAuthCredential[]; total: number };

export const credentialsApi = {
	list: ( page: number ): Promise< CredentialPage > =>
		apiFetch( { path: `${ REST_PATH }?page=${ page }` } ),

	create: (
		data: OAuthCredentialFormData
	): Promise< CreatedOAuthCredential > =>
		apiFetch( { path: REST_PATH, method: 'POST', data } ),

	update: (
		id: number,
		data: UpdateCredentialPayload
	): Promise< OAuthCredential > =>
		apiFetch( { path: `${ REST_PATH }/${ id }`, method: 'PATCH', data } ),

	remove: ( id: number ): Promise< { tokens_deleted: number } > =>
		apiFetch( { path: `${ REST_PATH }/${ id }`, method: 'DELETE' } ),
};
