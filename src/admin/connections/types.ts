export interface ConnectionUser {
	id: number;
	name: string;
	email: string;
	avatar_url: string;
	admin_edit_url: string;
}

export interface OAuthConnection {
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
	user: ConnectionUser;
	last_active_at: number;
}
