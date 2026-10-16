/**
 * SMOKE-04: OAuth when a CDN or firewall blocks /.well-known/ (#118).
 *
 * The plugin cannot stop a CDN from blocking requests, but it can (a) advertise
 * discovery URLs correctly and (b) tell the admin when they are unreachable.
 */
/**
 * Internal dependencies
 */
import { test, expect } from '../../fixtures';
import { wpCli } from '../../utils/wp-cli';
import { adminMain } from '../install/helpers';

test.describe(
	'Blocked discovery endpoints',
	{ tag: [ '@smoke', '@plugin-update' ] },
	() => {
		test(
			'SMOKE-04 unauthenticated MCP calls advertise a reachable resource_metadata URL',
			{ tag: '@wp-update' },
			async ( { anonApi } ) => {
				const res = await anonApi.post( '/wp-json/mcp/publishio', {
					headers: { 'Content-Type': 'application/json' },
					data: { jsonrpc: '2.0', id: 1, method: 'tools/list' },
				} );
				expect( res.status() ).toBe( 401 );

				const header = res.headers()[ 'www-authenticate' ] ?? '';
				expect( header ).toMatch( /^Bearer / );
				const metadataUrl = header.match(
					/resource_metadata="([^"]+)"/
				)?.[ 1 ];
				expect(
					metadataUrl,
					'WWW-Authenticate must carry resource_metadata'
				).toBeTruthy();

				const metadata = await anonApi.get( metadataUrl );
				expect( metadata.status() ).toBe( 200 );
			}
		);

		test(
			'SMOKE-04 admin is warned when /.well-known/ is blocked (#118)',
			{ tag: '@wp-update' },
			async ( { admin, page, anonApi } ) => {
				test.info().annotations.push( {
					type: 'issue',
					description:
						'https://github.com/rtCamp/publishio/issues/118',
				} );
				// Pending the self-check proposed in #118 (where it lives and its wording are still open).
				// When the self-check ships, remove fixme and adjust the locator below.
				test.fixme(
					true,
					'No well-known self-check in the admin yet (#118).'
				);

				// Block the discovery paths at the web server, as the nginx/CDN rule in #118 does (verified:
				// both return 403 while blocked). Note for a server-side wp_remote_get self-check: in wp-env
				// the container cannot reach its own home_url (loopback fails), so such a check needs a
				// way to tell "unreachable from here" apart from "blocked" before this test can pass.
				const rule =
					'RedirectMatch 403 ^/\\.well-known/oauth-(authorization-server|protected-resource)';
				const htaccess = wpCli( `eval 'echo ABSPATH . ".htaccess";'` );
				const original = wpCli(
					`eval 'echo file_get_contents( "${ htaccess }" );'`
				);
				wpCli(
					`eval 'file_put_contents( "${ htaccess }", ${ JSON.stringify(
						rule + '\n'
					) } . file_get_contents( "${ htaccess }" ) );'`
				);
				try {
					expect(
						(
							await anonApi.get(
								'/.well-known/oauth-authorization-server'
							)
						).status()
					).toBe( 403 );
					// The #118 comment proposes the Connections screen (today: "No AI apps have connected
					// yet"); point this at the Guide instead if the warning ships there.
					await admin.visitAdminPage(
						'admin.php',
						'page=publishio-connections'
					);
					await expect(
						adminMain( page ).getByText( /well-known/i )
					).toBeVisible();
				} finally {
					wpCli(
						`eval 'file_put_contents( "${ htaccess }", base64_decode( "${ Buffer.from(
							original
						).toString( 'base64' ) }" ) );'`
					);
				}
			}
		);
	}
);
