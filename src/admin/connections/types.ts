export interface ConnectionUser {
	id: number;
	name: string;
	email: string;
	avatar_url: string;
	admin_edit_url: string;
}

export type ConnectionOrder = 'asc' | 'desc';

export interface OAuthConnection {
	id: number;
	client_id: string;
	client_name: string;
	client_uri: string;
	user: ConnectionUser;
	connected_at: number;
	last_active_at: number;
}
