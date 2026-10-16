/**
 * SMOKE-09: OAuth rejects wrong credentials.
 */
/**
 * External dependencies
 */
import crypto from 'node:crypto';
/**
 * Internal dependencies
 */
import { test, expect } from '../../fixtures';
import { McpClient, PROTOCOL_VERSION } from '../../utils/mcp';
import {
	buildAuthorizeUrl,
	captureCallback,
	connect,
	createPkce,
	discover,
	exchangeCode,
	randomState,
	refreshTokens,
	registerClient,
	submitConsentViaApi,
} from '../../utils/oauth';
import { wpCli } from '../../utils/wp-cli';
import { evidence } from '../install/helpers';

/**
 * Change columns of a client's rows in Publishio's token table.
 *
 * @param {string} clientId Client whose tokens to change.
 * @param {Object} set      Column values, e.g. { access_expires_at: 1 }.
 */
function updateTokens( clientId, set ) {
	const payload = Buffer.from(
		JSON.stringify( { client_id: clientId, set } )
	).toString( 'base64' );
	const rows = wpCli(
		`eval '$d = json_decode( base64_decode( "${ payload }" ), true ); global $wpdb; echo (int) $wpdb->update( $wpdb->prefix . "publishio_oauth_tokens", $d["set"], [ "client_id" => $d["client_id"] ] );'`
	);
	expect(
		Number( rows ),
		`token rows updated for ${ clientId }`
	).toBeGreaterThan( 0 );
}

/**
 * Status, WWW-Authenticate header and body of an MCP initialize call.
 *
 * @param {import('@playwright/test').APIRequestContext} api     Request context.
 * @param {string}                                       url     MCP server URL.
 * @param {Object}                                       headers Extra headers.
 * @return {Promise<{status: number, wwwAuthenticate: string, body: string}>} The response.
 */
async function mcpInitialize( api, url, headers ) {
	const res = await api.post( url, {
		headers: {
			'Content-Type': 'application/json',
			Accept: 'application/json, text/event-stream',
			...headers,
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
	return {
		status: res.status(),
		wwwAuthenticate: res.headers()[ 'www-authenticate' ] ?? '',
		body: await res.text(),
	};
}

/**
 * Approve the consent screen over HTTP.
 *
 * @param {import('@playwright/test').APIRequestContext} adminApi  Logged-in admin.
 * @param {import('../../utils/oauth').Discovery}        discovery Discovery result.
 * @param {import('../../utils/oauth').RegisteredClient} client    Client.
 * @param {import('../../utils/oauth').Pkce}             pkce      PKCE pair.
 * @return {Promise<string>} The code.
 */
async function approve( adminApi, discovery, client, pkce ) {
	const redirect = await submitConsentViaApi(
		adminApi,
		buildAuthorizeUrl( discovery, client, pkce, randomState() )
	);
	const code = redirect.searchParams.get( 'code' );
	expect( code ).toBeTruthy();
	return code;
}

const expectError = async ( res, status, error ) => {
	expect( res.status(), await res.text() ).toBe( status );
	expect( ( await res.json() ).error ).toBe( error );
};

test.describe(
	'OAuth rejects wrong credentials',
	{ tag: [ '@smoke', '@plugin-update' ] },
	() => {
		test( 'SMOKE-09 the consent form refuses a missing or forged nonce', async ( {
			anonApi,
			adminApi,
		} ) => {
			const discovery = await discover( anonApi );
			const client = await registerClient( anonApi, discovery );
			const authorizeUrl = buildAuthorizeUrl(
				discovery,
				client,
				createPkce(),
				randomState()
			);
			expect(
				(
					await adminApi.get( authorizeUrl, { maxRedirects: 0 } )
				).status()
			).toBe( 200 );

			const params = Object.fromEntries(
				new URL( authorizeUrl ).searchParams
			);
			for ( const nonce of [ undefined, 'forged-nonce' ] ) {
				const res = await adminApi.post(
					authorizeUrl.split( '?' )[ 0 ],
					{
						form: {
							...params,
							consent: 'approve',
							...( nonce ? { _wpnonce: nonce } : {} ),
						},
						maxRedirects: 0,
					}
				);
				expect( res.status(), `nonce: ${ nonce ?? 'missing' }` ).toBe(
					403
				);
				expect( await res.text() ).toContain(
					'Invalid or expired form submission'
				);
			}
		} );

		test( 'SMOKE-09 a code is refused for another client, redirect_uri or resource', async ( {
			anonApi,
			adminApi,
		} ) => {
			const discovery = await discover( anonApi );
			const client = await registerClient( anonApi, discovery );
			const other = await registerClient( anonApi, discovery );

			let pkce = createPkce();
			let code = await approve( adminApi, discovery, client, pkce );
			await expectError(
				await exchangeCode(
					anonApi,
					discovery,
					other,
					code,
					pkce.verifier
				),
				400,
				'invalid_grant'
			);

			pkce = createPkce();
			code = await approve( adminApi, discovery, client, pkce );
			await expectError(
				await exchangeCode(
					anonApi,
					discovery,
					{
						...client,
						redirect_uris: [ 'http://localhost:9999/other' ],
					},
					code,
					pkce.verifier
				),
				400,
				'invalid_grant'
			);

			pkce = createPkce();
			code = await approve( adminApi, discovery, client, pkce );
			await expectError(
				await exchangeCode(
					anonApi,
					{
						...discovery,
						resource: 'https://example.com/wp-json/mcp/other',
					},
					client,
					code,
					pkce.verifier
				),
				400,
				'invalid_target'
			);
		} );

		test( 'SMOKE-09 an expired authorization code is refused', async ( {
			anonApi,
			adminApi,
		} ) => {
			const discovery = await discover( anonApi );
			const client = await registerClient( anonApi, discovery );
			const pkce = createPkce();
			const code = await approve( adminApi, discovery, client, pkce );

			// Codes live 120 s; expire it by moving its transient timeout into the past.
			const key = `_transient_timeout_publishio_oauth_code_${ crypto
				.createHash( 'sha256' )
				.update( code )
				.digest( 'hex' ) }`;
			wpCli( `option update ${ key } 1` );

			await expectError(
				await exchangeCode(
					anonApi,
					discovery,
					client,
					code,
					pkce.verifier
				),
				400,
				'invalid_grant'
			);
		} );

		test( 'SMOKE-09 refresh tokens: garbage, reused after rotation, or another client’s are refused', async ( {
			anonApi,
			adminApi,
		} ) => {
			const { discovery, client, tokens } = await connect(
				anonApi,
				adminApi
			);
			const other = await registerClient( anonApi, discovery );

			await expectError(
				await refreshTokens(
					anonApi,
					discovery,
					client,
					'not-a-refresh-token'
				),
				400,
				'invalid_grant'
			);
			await expectError(
				await refreshTokens(
					anonApi,
					discovery,
					other,
					tokens.refresh_token
				),
				400,
				'invalid_grant'
			);

			const first = await refreshTokens(
				anonApi,
				discovery,
				client,
				tokens.refresh_token
			);
			expect( first.status(), await first.text() ).toBe( 200 );
			await expectError(
				await refreshTokens(
					anonApi,
					discovery,
					client,
					tokens.refresh_token
				),
				400,
				'invalid_grant'
			);
		} );

		test( 'SMOKE-09 an expired access token is refused on MCP; refreshing restores access', async ( {
			anonApi,
			adminApi,
		} ) => {
			const { discovery, client, tokens } = await connect(
				anonApi,
				adminApi
			);
			updateTokens( client.client_id, { access_expires_at: 1 } );

			const expired = await mcpInitialize( anonApi, discovery.resource, {
				Authorization: `Bearer ${ tokens.access_token }`,
			} );
			expect( expired.status ).toBe( 401 );
			expect( expired.body ).toContain( 'rest_oauth_invalid_token' );

			const refreshed = await refreshTokens(
				anonApi,
				discovery,
				client,
				tokens.refresh_token
			);
			expect( refreshed.status(), await refreshed.text() ).toBe( 200 );
			await new McpClient(
				anonApi,
				discovery.resource,
				( await refreshed.json() ).access_token
			).initialize();
		} );

		test( 'SMOKE-09 a token issued for a different resource is refused', async ( {
			anonApi,
			adminApi,
		} ) => {
			const { discovery, client, tokens } = await connect(
				anonApi,
				adminApi
			);
			updateTokens( client.client_id, {
				resource: new URL(
					'/wp-json/mcp/some-other-server',
					discovery.resource
				).toString(),
			} );

			const res = await mcpInitialize( anonApi, discovery.resource, {
				Authorization: `Bearer ${ tokens.access_token }`,
			} );
			expect( res.status ).toBe( 401 );
			expect( res.body ).toContain( 'rest_oauth_invalid_audience' );
			expect( res.body ).toContain(
				'Token was not issued for this resource'
			);
		} );

		test( 'SMOKE-09 MCP rejects a made-up session ID, an unknown token and malformed Authorization headers', async ( {
			anonApi,
			connection,
		} ) => {
			const url = connection.discovery.resource;
			const token = connection.tokens.access_token;

			const res = await anonApi.post( url, {
				headers: {
					Authorization: `Bearer ${ token }`,
					'Content-Type': 'application/json',
					Accept: 'application/json, text/event-stream',
					'Mcp-Session-Id': `made-up-${ randomState() }`,
					'MCP-Protocol-Version': PROTOCOL_VERSION,
				},
				data: { jsonrpc: '2.0', id: 1, method: 'tools/list' },
			} );
			expect( res.status() ).toBe( 404 );
			const body = await res.json();
			expect( body.error?.code ).toBe( -32005 );
			expect( body ).not.toHaveProperty( 'result' );

			const unknown = await anonApi.post( url, {
				headers: {
					Authorization: 'Bearer not-a-real-token',
					'Content-Type': 'application/json',
				},
				data: { jsonrpc: '2.0', id: 1, method: 'tools/list' },
			} );
			expect( unknown.status() ).toBe( 401 );
			expect( ( await unknown.json() ).code ).toBe(
				'rest_oauth_invalid_token'
			);

			for ( const header of [
				token, // no scheme
				'Bearer ', // no token
				`Bearer Bearer ${ token }`,
				`Token ${ token }`,
				'Basic not-base64!!',
			] ) {
				const r = await mcpInitialize( anonApi, url, {
					Authorization: header,
				} );
				expect(
					r.status,
					`Authorization: ${ header.slice( 0, 20 ) }…`
				).toBe( 401 );
				expect( r.wwwAuthenticate ).toMatch(
					/^Bearer resource_metadata="/
				);
			}

			// The scheme is case-insensitive (RFC 7235).
			expect(
				(
					await mcpInitialize( anonApi, url, {
						Authorization: `bearer ${ token }`,
					} )
				).status
			).toBe( 200 );
		} );

		test(
			'SMOKE-09 an expired connection asks the user to authorize again',
			{ tag: '@wp-update' },
			async ( { anonApi, adminApi, requestUtils, page } ) => {
				const clientName = `E2E Expired ${ randomState().slice(
					0,
					6
				) }`;
				const { discovery, client, tokens } = await connect(
					anonApi,
					adminApi,
					{ client_name: clientName }
				);
				updateTokens( client.client_id, {
					access_expires_at: 1,
					refresh_expires_at: 1,
				} );

				const call = await mcpInitialize( anonApi, discovery.resource, {
					Authorization: `Bearer ${ tokens.access_token }`,
				} );
				expect( call.status ).toBe( 401 );
				expect( call.wwwAuthenticate ).toMatch(
					/^Bearer resource_metadata="/
				);

				await expectError(
					await refreshTokens(
						anonApi,
						discovery,
						client,
						tokens.refresh_token
					),
					400,
					'invalid_grant'
				);

				const listed = async () =>
					JSON.stringify(
						(
							await requestUtils.rest( {
								path: '/publishio/v1/connections',
							} )
						).items
					);
				expect( await listed() ).not.toContain( client.client_id );

				const pkce = createPkce();
				const callback = captureCallback( page );
				await page.goto(
					buildAuthorizeUrl( discovery, client, pkce, randomState() )
				);
				await expect(
					page.getByRole( 'heading', {
						name: 'Authorize Application',
					} )
				).toBeVisible();
				await expect(
					page.getByText( clientName ).first()
				).toBeVisible();
				await expect(
					page.getByRole( 'button', { name: 'Allow Access' } )
				).toBeVisible();
				await expect(
					page.getByRole( 'button', { name: 'Deny' } )
				).toBeVisible();
				await evidence(
					page,
					'SMOKE-09 consent screen after the connection expired'
				);

				await page
					.getByRole( 'button', { name: 'Allow Access' } )
					.click();
				await expect.poll( () => callback()?.toString() ).toBeTruthy();
				const fresh = await exchangeCode(
					anonApi,
					discovery,
					client,
					callback().searchParams.get( 'code' ),
					pkce.verifier
				);
				expect( fresh.status(), await fresh.text() ).toBe( 200 );
				await new McpClient(
					anonApi,
					discovery.resource,
					( await fresh.json() ).access_token
				).initialize();
				expect( await listed() ).toContain( client.client_id );
			}
		);
	}
);
