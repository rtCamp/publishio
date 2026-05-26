export interface OAuthCredential {
	id: number;
	client_id: string;
	client_name: string;
	source: string;
	redirect_uris: string[];
	grant_types: string;
	response_types: string;
	scope: string;
	registered_at: number;
	last_active_at: number | null;
}

/** Returned once on creation only. */
export type CreatedOAuthCredential = OAuthCredential & {
	client_secret: string;
};

export interface OAuthCredentialFormData {
	client_name: string;
	redirect_uris: string[];
}
