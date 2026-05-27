export interface OAuthCredential {
	id: number;
	client_id: string;
	client_name: string;
	source: string;
	redirect_uris: string[];
	grant_types: string;
	response_types: string;
	scope: string;
	client_uri: string | null;
	logo_uri: string | null;
	tos_uri: string | null;
	policy_uri: string | null;
	contacts: string[];
	software_id: string | null;
	software_version: string | null;
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
	client_uri: string;
	logo_uri: string;
	tos_uri: string;
	policy_uri: string;
	contacts: string;
	software_id: string;
	software_version: string;
}

export interface UpdateCredentialPayload {
	client_name: string;
	client_uri?: string | undefined;
	logo_uri?: string | undefined;
	tos_uri?: string | undefined;
	policy_uri?: string | undefined;
	contacts?: string[] | undefined;
	software_id?: string | undefined;
	software_version?: string | undefined;
}
