export interface OAuthClient {
	id: number;
	client_id: string;
	client_name: string;
	is_public: boolean;
	redirect_uris: string[];
	grant_types: string;
	response_types: string;
	scope: string;
	registered_at: number;
}

/** Returned once on creation for confidential clients only. */
export type CreatedOAuthClient = OAuthClient & { client_secret?: string };

export interface OAuthClientFormData {
	client_name: string;
	redirect_uris: string[];
	is_public: boolean;
	scope: string;
	grant_types: string[];
}
