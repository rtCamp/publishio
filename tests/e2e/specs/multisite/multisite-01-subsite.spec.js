/**
 * MULTISITE-01: connecting an AI app to a subsite of a network.
 *
 * Runs on a subdirectory network (localhost:8893/site2/) and a subdomain network (site2.localhost:8896).
 */

/**
 * Internal dependencies
 */
import { test, expect } from '../../fixtures';
import { currentSite } from '../../config/env';
import {
	TEST_REDIRECT_URI,
	buildAuthorizeUrl,
	createPkce,
	discoverFrom,
	exchangeCode,
	provisionMetadataClient,
	randomState,
	submitConsentViaApi,
	wellKnownUrl,
} from '../../utils/oauth';
import { McpClient, PROTOCOL_VERSION } from '../../utils/mcp';
import { STATUS_PAGE, expectLocalStatus } from '../../utils/status';
import { PASSWORD, loginApi, loginBrowser } from '../../utils/users';
import { wpCli, wpCliQuietly } from '../../utils/wp-cli';

// Letters and digits only: multisite rejects underscores in usernames.
const EDITOR = 'e2esubsiteeditor';

test.describe(
	'Subsite of a network',
	{ tag: [ '@multisite', '@plugin-update' ] },
	() => {
		let subsite;
		let issuer;
		let mcpUrl;

		test.beforeAll( () => {
			subsite = currentSite().subsite.url;
			issuer = subsite.replace( /\/$/, '' );
			mcpUrl = `${ issuer }/wp-json/mcp/publishio`;

			wpCliQuietly(
				`user create ${ EDITOR } ${ EDITOR }@example.test --role=editor --user_pass=${ PASSWORD } --url=${ subsite }`,
				/already registered/
			);
		} );

		test(
			"MULTISITE-01.1 an MCP client finds the subsite's own connection details",
			{ tag: '@wp-update' },
			async ( { anonApi } ) => {
				const discovery = await discoverFrom( anonApi, mcpUrl );

				expect( discovery.resource ).toBe( mcpUrl );
				expect( discovery.issuer ).toBe( issuer );
				expect( discovery.authorizationEndpoint ).toBe(
					`${ issuer }/publishio-oauth/authorize`
				);
				expect( discovery.tokenEndpoint ).toBe(
					`${ issuer }/wp-json/publishio-oauth/v1/token`
				);

				// Clients without the 401 header build this address from the MCP URL (RFC 9728).
				const resource = await anonApi.get(
					wellKnownUrl( mcpUrl, 'oauth-protected-resource' )
				);
				expect( resource.status() ).toBe( 200 );
				expect( await resource.json() ).toMatchObject( {
					resource: mcpUrl,
					authorization_servers: [ issuer ],
				} );
			}
		);

		test(
			'MULTISITE-01.2 an editor connects an app to the subsite, which then works on that site only',
			{ tag: '@wp-update' },
			async ( { anonApi, baseURL, playwright } ) => {
				const title = `Subsite post ${ randomState().slice( 0, 8 ) }`;
				wpCli(
					`post create --post_title='${ title }' --post_status=publish --url=${ subsite }`
				);

				const discovery = await discoverFrom( anonApi, mcpUrl );
				const client = provisionMetadataClient(
					{
						client_name: 'E2E Subsite Client',
						redirect_uris: [ TEST_REDIRECT_URI ],
					},
					subsite
				);
				const pkce = createPkce();
				const state = randomState();
				const editorApi = await loginApi(
					playwright,
					EDITOR,
					PASSWORD,
					subsite
				);

				const redirect = await submitConsentViaApi(
					editorApi,
					buildAuthorizeUrl( discovery, client, pkce, state )
				);
				await editorApi.dispose();
				expect( redirect.searchParams.get( 'state' ) ).toBe( state );
				expect( redirect.searchParams.get( 'iss' ) ).toBe( issuer );

				const tokenRes = await exchangeCode(
					anonApi,
					discovery,
					client,
					redirect.searchParams.get( 'code' ),
					pkce.verifier
				);
				expect( tokenRes.status(), await tokenRes.text() ).toBe( 200 );
				const token = ( await tokenRes.json() ).access_token;

				const mcp = new McpClient( anonApi, mcpUrl, token );
				await mcp.initialize();
				const found = await mcp.callTool( 'publishio/search-posts', {
					search: title,
				} );
				expect( found.isError, found.text ).toBe( false );
				expect( found.data.map( ( post ) => post.title ) ).toEqual( [
					title,
				] );

				const mainSite = await anonApi.post(
					new URL( '/wp-json/mcp/publishio', baseURL ).toString(),
					{
						headers: { Authorization: `Bearer ${ token }` },
						data: {
							jsonrpc: '2.0',
							id: 1,
							method: 'initialize',
							params: {
								protocolVersion: PROTOCOL_VERSION,
								capabilities: {},
								clientInfo: {
									name: 'publishio-e2e',
									version: '1.0.0',
								},
							},
						},
					}
				);
				expect( mainSite.status() ).toBe( 401 );
			}
		);

		test( 'MULTISITE-01.3 the Status page on the subsite passes', async ( {
			browser,
		} ) => {
			const context = await loginBrowser(
				browser,
				EDITOR,
				PASSWORD,
				subsite
			);
			const page = await context.newPage();

			await page.goto(
				`${ subsite }wp-admin/admin.php?${ STATUS_PAGE }`
			);
			await expectLocalStatus( page );

			await context.close();
		} );
	}
);
