/**
 * SMOKE-03: OAuth connect flow.
 */
/**
 * Internal dependencies
 */
import { test, expect } from '../../fixtures';
import {
	TEST_CLIENT_HOST,
	TEST_REDIRECT_URI,
	captureCallback,
	buildAuthorizeUrl,
	connect,
	createPkce,
	discover,
	exchangeCode,
	randomState,
	refreshTokens,
	registerClient,
	submitConsentViaApi,
} from '../../utils/oauth';
import { McpClient } from '../../utils/mcp';
import { ensureUser, loginApi } from '../../utils/users';

test.describe(
	'OAuth discovery',
	{ tag: [ '@smoke', '@plugin-update' ] },
	() => {
		test(
			'SMOKE-03 publishes RFC 9728 and RFC 8414 metadata',
			{ tag: '@wp-update' },
			async ( { anonApi, baseURL } ) => {
				const mcpUrl = new URL(
					'/wp-json/mcp/publishio',
					baseURL
				).toString();

				for ( const p of [
					'/.well-known/oauth-protected-resource',
					'/.well-known/oauth-protected-resource/wp-json/mcp/publishio',
				] ) {
					const res = await anonApi.get( p );
					expect( res.status(), p ).toBe( 200 );
					const json = await res.json();
					expect( json.resource ).toBe( mcpUrl );
					expect( json.authorization_servers ).toHaveLength( 1 );
					expect( json.bearer_methods_supported ).toEqual( [
						'header',
					] );
				}

				const as = await (
					await anonApi.get(
						'/.well-known/oauth-authorization-server'
					)
				).json();
				expect( as ).toMatchObject( {
					response_types_supported: [ 'code' ],
					code_challenge_methods_supported: [ 'S256' ],
					scopes_supported: [ 'mcp:read', 'mcp:write' ],
				} );
				expect( as.authorization_endpoint ).toContain(
					'/publishio-oauth/authorize'
				);
				expect( as.token_endpoint ).toContain(
					'/publishio-oauth/v1/token'
				);
				expect( as.client_id_metadata_document_supported ).toBe( true );
				expect( as.registration_endpoint ).toBeUndefined();
			}
		);
	}
);

test.describe(
	'OAuth connect (browser)',
	{ tag: [ '@smoke', '@plugin-update' ] },
	() => {
		test(
			'SMOKE-03 approve on the consent screen, call MCP, then revoke from Connections',
			{ tag: '@wp-update' },
			async ( { page, admin, anonApi } ) => {
				const discovery = await discover( anonApi );
				// Unique per run so leftovers from earlier runs never match.
				const clientName = `E2E Claude Stand-in ${ randomState().slice(
					0,
					6
				) }`;
				const client = await registerClient( anonApi, discovery, {
					client_name: clientName,
				} );
				const pkce = createPkce();
				const state = randomState();

				// Nothing listens on the callback; capture the redirect instead.
				const callback = captureCallback( page );

				await page.goto(
					buildAuthorizeUrl( discovery, client, pkce, state )
				);

				await expect(
					page.getByRole( 'heading', {
						name: 'Authorize Application',
					} )
				).toBeVisible();
				await expect(
					page.getByText( clientName ).first()
				).toBeVisible();
				await expect( page.getByText( 'Signed in as' ) ).toBeVisible();
				await expect(
					page.getByText( TEST_REDIRECT_URI )
				).toBeVisible();
				await expect(
					page.getByText( `(${ TEST_CLIENT_HOST })` )
				).toBeVisible();
				await expect(
					page.getByText( /runs on your computer/ )
				).toBeVisible();

				await page
					.getByRole( 'button', { name: 'Allow Access' } )
					.click();
				await expect.poll( () => callback()?.toString() ).toBeTruthy();

				expect( callback().searchParams.get( 'state' ) ).toBe( state );
				const code = callback().searchParams.get( 'code' );
				expect( code ).toBeTruthy();

				const tokenRes = await exchangeCode(
					anonApi,
					discovery,
					client,
					code,
					pkce.verifier
				);
				expect( tokenRes.status(), await tokenRes.text() ).toBe( 200 );
				const tokens = await tokenRes.json();
				expect( tokens.token_type ).toBe( 'Bearer' );
				expect( tokens.expires_in ).toBeGreaterThan( 0 );

				const mcp = new McpClient(
					anonApi,
					discovery.resource,
					tokens.access_token
				);
				await mcp.initialize();

				await admin.visitAdminPage(
					'admin.php',
					'page=publishio-connections'
				);
				const row = page
					.getByRole( 'row' )
					.filter( { hasText: clientName } );
				await expect( row ).toBeVisible();

				// DataViews shows Remove inline or in an "Actions" menu.
				const inlineRemove = row.getByRole( 'button', {
					name: 'Remove',
				} );
				const actions = row.getByRole( 'button', { name: 'Actions' } );
				await expect( inlineRemove.or( actions ) ).toBeVisible();
				if ( await inlineRemove.isVisible() ) {
					await inlineRemove.click();
				} else {
					await actions.click();
					await page
						.getByRole( 'menuitem', { name: 'Remove' } )
						.click();
				}
				const dialog = page.getByRole( 'dialog' );
				await expect(
					dialog.getByText( 'This action cannot be undone.' )
				).toBeVisible();
				await dialog
					.getByRole( 'button', { name: 'Remove', exact: true } )
					.click();
				await expect(
					page
						.getByTestId( 'snackbar' )
						.filter( { hasText: 'Connection removed.' } )
				).toBeVisible();

				const after = await new McpClient(
					anonApi,
					discovery.resource,
					tokens.access_token
				).send( 'initialize', {
					protocolVersion: '2025-06-18',
					capabilities: {},
					clientInfo: { name: 'x', version: '1' },
				} );
				expect( after.status() ).toBe( 401 );
			}
		);

		test(
			'SMOKE-03 deny returns access_denied to the client',
			{ tag: '@wp-update' },
			async ( { page, anonApi } ) => {
				const discovery = await discover( anonApi );
				const client = await registerClient( anonApi, discovery );
				const state = randomState();

				const callback = captureCallback( page );

				await page.goto(
					buildAuthorizeUrl( discovery, client, createPkce(), state )
				);
				await page.getByRole( 'button', { name: 'Deny' } ).click();

				await expect.poll( () => callback()?.toString() ).toBeTruthy();
				expect( callback().searchParams.get( 'error' ) ).toBe(
					'access_denied'
				);
				expect( callback().searchParams.get( 'state' ) ).toBe( state );
				expect( callback().searchParams.get( 'code' ) ).toBeNull();
			}
		);

		test(
			'SMOKE-03 logged-out users are sent to wp-login and back',
			{ tag: '@wp-update' },
			async ( { browser, anonApi } ) => {
				const discovery = await discover( anonApi );
				const client = await registerClient( anonApi, discovery );
				const context = await browser.newContext( {
					storageState: { cookies: [], origins: [] },
				} );
				const page = await context.newPage();

				await page.goto(
					buildAuthorizeUrl(
						discovery,
						client,
						createPkce(),
						randomState()
					)
				);
				await expect( page ).toHaveURL(
					/wp-login\.php.*redirect_to=.*publishio-oauth/
				);

				await context.close();
			}
		);
	}
);

test.describe(
	'OAuth security (HTTP)',
	{ tag: [ '@smoke', '@plugin-update' ] },
	() => {
		test( 'SMOKE-03 authorize rejects a client_id that is not a metadata document URL', async ( {
			anonApi,
			adminApi,
		} ) => {
			const discovery = await discover( anonApi );
			expect(
				discovery.cimd,
				'server must advertise client_id_metadata_document_supported'
			).toBe( true );
			expect(
				discovery.registrationEndpoint,
				'dynamic client registration was removed'
			).toBeUndefined();

			const client = await registerClient( anonApi, discovery );
			const res = await adminApi.get(
				buildAuthorizeUrl(
					discovery,
					{ ...client, client_id: 'legacy-dcr-client-id' },
					createPkce(),
					randomState()
				),
				{ maxRedirects: 0 }
			);
			expect( res.status() ).toBe( 400 );
			expect( await res.text() ).toContain(
				'client_id must be the HTTPS URL of a client metadata document'
			);
		} );

		test( 'SMOKE-03 authorize rejects an unregistered redirect_uri', async ( {
			anonApi,
			adminApi,
		} ) => {
			const discovery = await discover( anonApi );
			const client = await registerClient( anonApi, discovery );
			const url = buildAuthorizeUrl(
				discovery,
				client,
				createPkce(),
				randomState(),
				{
					redirect_uri: 'http://localhost:9999/other',
				}
			);
			const res = await adminApi.get( url, { maxRedirects: 0 } );
			expect( res.status() ).toBe( 400 );
			expect( await res.text() ).toContain( 'invalid_redirect_uri' );
		} );

		test( 'SMOKE-03 authorize requires PKCE S256 and a matching resource', async ( {
			anonApi,
			adminApi,
		} ) => {
			const discovery = await discover( anonApi );
			const client = await registerClient( anonApi, discovery );
			const pkce = createPkce();

			const cases = [
				[ { code_challenge: '' }, 'PKCE code_challenge is required' ],
				[ { code_challenge_method: 'plain' }, 'Only S256' ],
				[ { state: '' }, 'state parameter is required' ],
				[ { resource: 'https://example.com/mcp' }, 'invalid_target' ],
			];
			for ( const [ override, message ] of cases ) {
				const res = await adminApi.get(
					buildAuthorizeUrl(
						discovery,
						client,
						pkce,
						randomState(),
						override
					),
					{ maxRedirects: 0 }
				);
				expect( res.status(), JSON.stringify( override ) ).toBe( 400 );
				expect( await res.text() ).toContain( message );
			}
		} );

		test( 'SMOKE-03 code is single-use and bound to the PKCE verifier', async ( {
			anonApi,
			adminApi,
		} ) => {
			const discovery = await discover( anonApi );
			const client = await registerClient( anonApi, discovery );

			const pkce1 = createPkce();
			const r1 = await submitConsentViaApi(
				adminApi,
				buildAuthorizeUrl( discovery, client, pkce1, randomState() )
			);
			const wrong = await exchangeCode(
				anonApi,
				discovery,
				client,
				r1.searchParams.get( 'code' ),
				createPkce().verifier
			);
			expect( wrong.status() ).toBe( 400 );
			expect( ( await wrong.json() ).error ).toBe( 'invalid_grant' );

			const pkce2 = createPkce();
			const r2 = await submitConsentViaApi(
				adminApi,
				buildAuthorizeUrl( discovery, client, pkce2, randomState() )
			);
			const code = r2.searchParams.get( 'code' );
			expect(
				(
					await exchangeCode(
						anonApi,
						discovery,
						client,
						code,
						pkce2.verifier
					)
				).status()
			).toBe( 200 );
			const reuse = await exchangeCode(
				anonApi,
				discovery,
				client,
				code,
				pkce2.verifier
			);
			expect( reuse.status() ).toBe( 400 );
			expect( ( await reuse.json() ).error ).toBe( 'invalid_grant' );
		} );

		test( 'SMOKE-03 refresh token issues a working access token', async ( {
			anonApi,
			adminApi,
		} ) => {
			const { discovery, client, tokens } = await connect(
				anonApi,
				adminApi
			);

			const res = await refreshTokens(
				anonApi,
				discovery,
				client,
				tokens.refresh_token
			);
			expect( res.status(), await res.text() ).toBe( 200 );
			const fresh = await res.json();
			expect( fresh.access_token ).not.toBe( tokens.access_token );

			await new McpClient(
				anonApi,
				discovery.resource,
				fresh.access_token
			).initialize();
		} );

		test(
			'SMOKE-03 subscribers cannot authorize clients',
			{ tag: '@wp-update' },
			async ( { anonApi, requestUtils, playwright } ) => {
				const username = await ensureUser( requestUtils, 'subscriber' );
				const subscriberApi = await loginApi( playwright, username );
				const discovery = await discover( anonApi );
				const client = await registerClient( anonApi, discovery );

				const res = await subscriberApi.get(
					buildAuthorizeUrl(
						discovery,
						client,
						createPkce(),
						randomState()
					),
					{ maxRedirects: 0 }
				);
				expect( res.status() ).toBe( 403 );
				expect( await res.text() ).toContain(
					'You do not have permission to authorize MCP clients.'
				);
				await subscriberApi.dispose();
			}
		);
	}
);
