/**
 * SMOKE-10: the Status page.
 */
/**
 * Internal dependencies
 */
import { test, expect } from '../../fixtures';
import { branchHeader } from '../../utils/plugin-zip';
import { ensureUser, loginBrowser } from '../../utils/users';
import { wpCli } from '../../utils/wp-cli';
import { collectErrors } from '../install/helpers';
import { expectLocalStatus, STATUS_PAGE } from '../../utils/status';

const APPS = [ 'claude', 'claude_code', 'chatgpt' ];

/**
 * A Status page check in the problems list (or in "Passed" once expanded).
 * @param {import('@playwright/test').Page} page Page showing the Status screen.
 * @param {string}                          name Check name, e.g. "Public address".
 */
const checkItem = ( page, name ) =>
	page
		.getByRole( 'main' )
		.getByRole( 'listitem' )
		.filter( {
			has: page.getByRole( 'heading', { level: 3, name } ),
		} );

test.describe( 'Status page', { tag: [ '@smoke', '@plugin-update' ] }, () => {
	test(
		'SMOKE-10 explains each problem, how to fix it, and what the checks cannot see',
		{ tag: '@wp-update' },
		async ( { admin, page } ) => {
			const errors = collectErrors( page );
			const baseURL = test.info().project.use.baseURL;
			await admin.visitAdminPage( 'admin.php', STATUS_PAGE );
			await expectLocalStatus( page );

			const address = checkItem( page, 'Public address' );
			await expect( address ).toContainText(
				'This address only works on this computer'
			);
			await expect( address ).toContainText( 'How to fix' );
			await expect( address ).toContainText(
				'put the site on a public https:// address'
			);
			await address
				.getByRole( 'button', { name: 'Technical details' } )
				.click();
			await expect( address ).toContainText(
				`${ baseURL }/wp-json/mcp/publishio`
			);

			const caveats = page.getByRole( 'button', {
				name: "What these checks can't see",
			} );
			await caveats.click();
			await expect( caveats ).toHaveAttribute( 'aria-expanded', 'true' );
			const main = page.getByRole( 'main' );
			for ( const command of [
				`curl -i -X POST "${ baseURL }/wp-json/mcp/publishio"`,
				`curl -i "${ baseURL }/.well-known/oauth-protected-resource/wp-json/mcp/publishio"`,
				`curl -i "${ baseURL }/.well-known/oauth-authorization-server"`,
			] ) {
				await expect( main ).toContainText( command );
			}

			expect( errors ).toEqual( [] );
		}
	);

	test(
		'SMOKE-10 Show report gives a copyable summary of every check',
		{ tag: '@wp-update' },
		async ( { admin, page } ) => {
			await admin.visitAdminPage( 'admin.php', STATUS_PAGE );
			await expectLocalStatus( page );

			await page
				.getByRole( 'button', { name: 'Show report' } )
				.first()
				.click();
			const report = page.getByRole( 'dialog', {
				name: 'Status report',
			} );
			await expect( report ).toBeVisible();
			for ( const text of [
				'Public address',
				'MCP server',
				'Connection details',
				'App verification',
				'Access after approval',
				branchHeader().version,
			] ) {
				await expect(
					report,
					`report mentions ${ text }`
				).toContainText( text );
			}
			await expect(
				report.getByRole( 'button', { name: 'Copy report' } )
			).toBeVisible();
			await report
				.getByRole( 'button', { name: 'Close' } )
				.first()
				.click();
			await expect( report ).toBeHidden();
		}
	);

	test(
		'SMOKE-10 status REST endpoints report the site facts and verify the AI apps',
		{ tag: '@wp-update' },
		async ( { requestUtils, anonApi } ) => {
			const facts = await requestUtils.rest( {
				path: '/publishio/v1/status',
			} );
			expect( facts ).toMatchObject( {
				publishio_version: branchHeader().version,
				permalinks: 'pretty',
				mcp_server: true,
			} );
			expect( facts.mcp_url ).toMatch( /\/wp-json\/mcp\/publishio$/ );
			expect( facts.tools ).toBeGreaterThan( 0 );

			// Remote metadata fetches have a 5 s limit; retry only network timeouts.
			let apps = [];
			for ( let attempt = 0; attempt < 3; attempt++ ) {
				( { apps } = await requestUtils.rest( {
					path: '/publishio/v1/status/apps',
				} ) );
				if ( ! apps.some( ( a ) => a.code === 'connection_failed' ) ) {
					break;
				}
			}
			expect( apps.map( ( a ) => a.id ).sort() ).toEqual(
				[ ...APPS ].sort()
			);
			for ( const app of apps ) {
				expect( app, JSON.stringify( app ) ).toMatchObject( {
					ok: true,
					code: 'ok',
				} );
			}

			for ( const path of [ '/status', '/status/apps' ] ) {
				const res = await anonApi.get(
					`/wp-json/publishio/v1${ path }`
				);
				expect( res.status(), `logged out: ${ path }` ).toBe( 401 );
			}
		}
	);

	test(
		'SMOKE-10 contributors can open the Status page; subscribers cannot',
		{ tag: '@wp-update' },
		async ( { browser, requestUtils } ) => {
			const restStatus = async ( context ) => {
				const page = await context.newPage();
				await page.goto( '/wp-admin/profile.php' );
				const nonce = await (
					await context.request.get(
						'/wp-admin/admin-ajax.php?action=rest-nonce'
					)
				).text();
				const res = await context.request.get(
					'/wp-json/publishio/v1/status',
					{ headers: { 'X-WP-Nonce': nonce } }
				);
				await page.close();
				return res.status();
			};

			const contributor = await loginBrowser(
				browser,
				await ensureUser( requestUtils, 'contributor' )
			);
			const page = await contributor.newPage();
			await page.goto( `/wp-admin/admin.php?${ STATUS_PAGE }` );
			await expect(
				page
					.getByRole( 'main' )
					.getByRole( 'heading', { level: 1, name: 'Status' } )
			).toBeVisible();
			expect( await restStatus( contributor ) ).toBe( 200 );
			await contributor.close();

			const subscriber = await loginBrowser(
				browser,
				await ensureUser( requestUtils, 'subscriber' )
			);
			const denied = await subscriber.newPage();
			await denied.goto( `/wp-admin/admin.php?${ STATUS_PAGE }` );
			await expect(
				denied.getByText( /not allowed to access this page/i )
			).toBeVisible();
			expect( await restStatus( subscriber ) ).toBe( 403 );
			await subscriber.close();
		}
	);

	test(
		'SMOKE-10 app verification fails clearly when WordPress blocks outgoing connections',
		{ tag: '@wp-update' },
		async ( { admin, page, requestUtils } ) => {
			wpCli(
				'config set WP_HTTP_BLOCK_EXTERNAL true --raw --type=constant'
			);
			try {
				const { apps } = await requestUtils.rest( {
					path: '/publishio/v1/status/apps',
				} );
				for ( const app of apps ) {
					expect( app, JSON.stringify( app ) ).toMatchObject( {
						ok: false,
						code: 'blocked_by_wordpress',
					} );
				}

				await admin.visitAdminPage( 'admin.php', STATUS_PAGE );
				await expect(
					page.getByText( /^Checked .* from this browser/ )
				).toBeVisible( { timeout: 30_000 } );
				const verification = checkItem( page, 'App verification' );
				await expect( verification ).toContainText( "Couldn't verify" );
				await expect( verification ).toContainText(
					'WordPress is set to block outgoing connections.'
				);
				await expect( verification ).toContainText(
					'WP_ACCESSIBLE_HOSTS'
				);
				await expect(
					page.getByRole( 'main' ).getByRole( 'heading', {
						level: 2,
						name: '2 problems',
					} )
				).toBeVisible();
			} finally {
				wpCli( 'config delete WP_HTTP_BLOCK_EXTERNAL --type=constant' );
			}
		}
	);
} );
