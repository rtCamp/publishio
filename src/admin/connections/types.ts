export interface ConnectionUser {
	id: number;
	name: string;
	email: string;
	avatar_url: string;
	admin_edit_url: string;
}

export interface OAuthConnection {
	id: number;
	client_id: string;
	client_name: string;
	is_public: boolean;
	redirect_uris: string[];
	grant_types: string;
	response_types: string;
	scope: string;
	registered_at: number;
	users: ConnectionUser[];
	last_active_at: number | null;
}

/** Returned once on creation for confidential connections only. */
export type CreatedOAuthConnection = OAuthConnection & {
	client_secret?: string;
};

export interface OAuthConnectionFormData {
	client_name: string;
	redirect_uris: string[];
	is_public: boolean;
	scope: string;
	grant_types: string[];
}
