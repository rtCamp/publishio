/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import type { Field } from '@wordpress/dataviews';

/**
 * Internal dependencies
 */
import type { OAuthConnection } from './types';
import { ConnectionFieldName } from './ConnectionFieldName';
import { ConnectionFieldUsers } from './ConnectionFieldUsers';
import { ConnectionFieldRegistered } from './ConnectionFieldRegistered';

export const connectionFields: Field< OAuthConnection >[] = [
	{
		id: 'client_name',
		label: __( 'Name', 'rtcamp-publish-with-ai' ),
		enableSorting: true,
		enableGlobalSearch: true,
		getValue: ( { item } ) => item.client_name,
		render: ( { item } ) => <ConnectionFieldName item={ item } />,
	},
	{
		id: 'client_id',
		label: __( 'Client ID', 'rtcamp-publish-with-ai' ),
		getValue: ( { item } ) => item.client_id,
	},
	{
		id: 'users',
		label: __( 'Users', 'rtcamp-publish-with-ai' ),
		getValue: ( { item } ) => item.users.map( ( u ) => u.name ).join( ' ' ),
		enableGlobalSearch: true,
		render: ( { item } ) => <ConnectionFieldUsers users={ item.users } />,
	},
	{
		id: 'registered_at',
		label: __( 'Registered', 'rtcamp-publish-with-ai' ),
		enableSorting: true,
		getValue: ( { item } ) =>
			new Date( item.registered_at * 1000 ).toISOString(),
		render: ( { item } ) => <ConnectionFieldRegistered item={ item } />,
	},
];
