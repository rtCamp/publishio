/** On multisite, a super admin or another user can have a connection without being a member of the site. */
export type ConnectionMembership = 'member' | 'super_admin' | 'not_member';

export interface ConnectionUser {
	id: number;
	name: string;
	email: string;
	avatar_url: string;
	/** Null when the viewer can't edit the user, as for site admins on multisite. */
	admin_edit_url: string | null;
	membership: ConnectionMembership;
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
