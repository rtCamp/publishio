/**
 * SMOKE-04: OAuth when /.well-known/ is blocked (#118).
 */
/**
 * Internal dependencies
 */
import { test, expect } from '../../fixtures';
import { wpCli } from '../../utils/wp-cli';
import { STATUS_PAGE, waitForChecks } from '../../utils/status';

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
					await admin.visitAdminPage( 'admin.php', STATUS_PAGE );
					await waitForChecks( page );
					const details = page
						.getByRole( 'main' )
						.getByRole( 'listitem' )
						.filter( {
							has: page.getByRole( 'heading', {
								level: 3,
								name: 'Connection details',
							} ),
						} );
					await expect( details ).toContainText(
						"AI apps can't read them"
					);
					await expect( details ).toContainText(
						'Your web server blocked the connection details before WordPress saw them.'
					);
					await expect( details ).toContainText(
						'/.well-known/oauth-'
					);
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
