/**
 * ISSUE: regression tests for GitHub issues.
 */
/**
 * External dependencies
 */
import fs from 'node:fs';
/**
 * Internal dependencies
 */
import { test, expect } from '../../fixtures';
import { connect, refreshTokens } from '../../utils/oauth';
import { PROTOCOL_VERSION } from '../../utils/mcp';
import { wpCli } from '../../utils/wp-cli';

const issue = ( n ) => ( {
	type: 'issue',
	description: `https://github.com/rtCamp/publishio/issues/${ n }`,
} );

/**
 * Status of an MCP initialize call with the given Authorization header.
 *
 * @param {import('@playwright/test').APIRequestContext} api           Request context.
 * @param {string}                                       endpoint      MCP server URL.
 * @param {string}                                       authorization Authorization header value.
 * @return {Promise<number>} HTTP status.
 */
async function initializeStatus( api, endpoint, authorization ) {
	const res = await api.post( endpoint, {
		headers: {
			Authorization: authorization,
			'Content-Type': 'application/json',
			Accept: 'application/json, text/event-stream',
		},
		data: {
			jsonrpc: '2.0',
			id: 1,
			method: 'initialize',
			params: {
				protocolVersion: PROTOCOL_VERSION,
				capabilities: {},
				clientInfo: { name: 'publishio-e2e', version: '1.0.0' },
			},
		},
	} );
	return res.status();
}

test.describe( 'GitHub issues', { tag: [ '@smoke', '@plugin-update' ] }, () => {
	test(
		'ISSUE-126 every script dependency in build/*.asset.php is registered on this WordPress',
		{ tag: '@wp-update' },
		async () => {
			test.info().annotations.push( issue( 126 ) );
			const wp = wpCli( 'core version' );

			// WordPress silently drops a script with an unregistered dependency.
			const missing = JSON.parse(
				wpCli(
					`eval '$out = []; foreach ( glob( WP_PLUGIN_DIR . "/publishio/build/*.asset.php" ) as $f ) { $a = include $f; foreach ( $a["dependencies"] as $d ) { if ( ! wp_script_is( $d, "registered" ) ) { $out[] = basename( $f ) . ": " . $d; } } } echo wp_json_encode( $out );'`
				)
			);
			expect(
				missing,
				`unregistered script dependencies on WordPress ${ wp }`
			).toEqual( [] );
		}
	);

	test(
		'ISSUE-118.1 MCP clients can fall back to Application Passwords when discovery is blocked',
		{ tag: '@wp-update' },
		async ( { anonApi, connection } ) => {
			test.info().annotations.push( issue( 118 ) );
			const available = wpCli(
				`eval 'echo wp_is_application_passwords_available() ? "yes" : "no";'`
			);
			test.skip(
				available !== 'yes',
				'Application Passwords are disabled on this site (they need HTTPS or a local environment type).'
			);

			const password = wpCli(
				`user application-password create admin "E2E MCP ${ Date.now() }" --porcelain`
			);
			const endpoint = connection.discovery.resource;
			const basic = `Basic ${ Buffer.from(
				`admin:${ password }`
			).toString( 'base64' ) }`;
			expect(
				await initializeStatus( anonApi, endpoint, basic ),
				'Application Password'
			).toBe( 200 );
			expect(
				await initializeStatus(
					anonApi,
					endpoint,
					`Basic ${ Buffer.from( 'admin:wrong-password' ).toString(
						'base64'
					) }`
				),
				'wrong Application Password'
			).toBe( 401 );
		}
	);

	test( 'ISSUE-118.2 the readme documents that /.well-known/ must reach WordPress', async () => {
		test.info().annotations.push( issue( 118 ) );
		const readme = fs.readFileSync( 'readme.txt', 'utf8' );
		expect( readme ).toMatch( /\/\.well-known\// );
	} );

	test( 'ISSUE-11 revoking one connection leaves the user’s other connections working', async ( {
		anonApi,
		adminApi,
		requestUtils,
	} ) => {
		test.info().annotations.push( issue( 11 ) );
		const first = await connect( anonApi, adminApi );
		const second = await connect( anonApi, adminApi );

		const items = (
			await requestUtils.rest( { path: '/publishio/v1/connections' } )
		).items;
		const firstRow = items.find(
			( c ) => c.client_id === first.client.client_id
		);
		expect( firstRow, 'first connection is listed' ).toBeTruthy();

		await requestUtils.rest( {
			method: 'DELETE',
			path: `/publishio/v1/connections/${ firstRow.id }`,
			params: { user_id: firstRow.user.id },
		} );

		expect(
			await initializeStatus(
				anonApi,
				first.discovery.resource,
				`Bearer ${ first.tokens.access_token }`
			),
			'revoked token'
		).toBe( 401 );
		expect(
			await initializeStatus(
				anonApi,
				second.discovery.resource,
				`Bearer ${ second.tokens.access_token }`
			),
			'other connection'
		).toBe( 200 );

		const after = JSON.stringify(
			(
				await requestUtils.rest( {
					path: '/publishio/v1/connections',
				} )
			).items
		);
		expect( after ).not.toContain( first.client.client_id );
		expect( after ).toContain( second.client.client_id );

		await expect(
			requestUtils.rest( {
				method: 'DELETE',
				path: `/publishio/v1/connections/${ firstRow.id }`,
				params: { user_id: firstRow.user.id },
			} )
		).rejects.toMatchObject( { code: 'not_found' } );
	} );

	test( 'ISSUE-12 the standalone MCP server exposes only Publishio tools at its own URL', async ( {
		mcp,
		connection,
	} ) => {
		test.info().annotations.push( issue( 12 ) );
		expect( new URL( connection.discovery.resource ).pathname ).toBe(
			'/wp-json/mcp/publishio'
		);
		const names = ( await mcp.listTools() ).map( ( t ) => t.name );
		expect( names.length ).toBeGreaterThan( 0 );
		expect(
			names.filter( ( n ) => ! n.startsWith( 'publishio-' ) ),
			'tools from other servers or plugins'
		).toEqual( [] );
	} );

	test( 'ISSUE-14 a token refresh records the client’s last activity', async ( {
		anonApi,
		adminApi,
		requestUtils,
	} ) => {
		test.info().annotations.push( issue( 14 ) );
		const { discovery, client, tokens } = await connect(
			anonApi,
			adminApi
		);
		const row = async () =>
			(
				await requestUtils.rest( {
					path: '/publishio/v1/connections',
				} )
			).items.filter( ( c ) => c.client_id === client.client_id );

		const [ before ] = await row();
		expect( before.last_active_at ).toBeGreaterThanOrEqual(
			before.connected_at
		);

		// Timestamps are in seconds: start the refresh in a later second than last_active_at.
		await expect
			.poll( () => Math.floor( Date.now() / 1000 ), {
				intervals: [ 50 ],
			} )
			.toBeGreaterThan( before.last_active_at );
		const refreshed = await refreshTokens(
			anonApi,
			discovery,
			client,
			tokens.refresh_token
		);
		expect( refreshed.status(), await refreshed.text() ).toBe( 200 );

		const rows = await row();
		expect( rows, 'still one connection after a refresh' ).toHaveLength(
			1
		);
		expect( rows[ 0 ].connected_at ).toBe( before.connected_at );
		expect( rows[ 0 ].last_active_at ).toBeGreaterThan(
			before.last_active_at
		);
	} );
} );
