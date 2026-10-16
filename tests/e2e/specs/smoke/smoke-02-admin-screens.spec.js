/**
 * SMOKE-02: every admin screen renders. Run on WordPress 6.9 (minimum) and latest.
 * Regression for #126 (Connections screen blank on WordPress 6.9).
 *
 * On single sites, INSTALL-07 checks that the Guide and Connections screens render, from the
 * built zip. Those two checks are tagged @multisite only here, because there is no install
 * project on a multisite network. The other tests (status line, MCP URL, permissions) run everywhere.
 */
/**
 * External dependencies
 */

/**
 * Internal dependencies
 */
import { test, expect } from '../../fixtures';
import { ensureUser, loginBrowser } from '../../utils/users';
import { adminMain, collectErrors } from '../install/helpers';
import {
	expectLocalGuideStatus,
	expectLocalStatus,
	STATUS_PAGE,
} from '../../utils/status';

test.describe( 'Admin screens', { tag: '@plugin-update' }, () => {
	test(
		'SMOKE-02 Guide screen renders',
		{ tag: [ '@multisite', '@wp-update' ] },
		async ( { admin, page } ) => {
			const errors = collectErrors( page );
			await admin.visitAdminPage( 'admin.php', 'page=publishio' );

			const root = adminMain( page );
			await expect(
				root.getByRole( 'heading', { level: 1, name: 'Guide' } )
			).toBeVisible();
			await expect(
				root.getByRole( 'heading', { name: 'Claude AI' } )
			).toBeVisible();
			await expect(
				root.getByRole( 'heading', { name: 'ChatGPT' } )
			).toBeVisible();

			expect( errors ).toEqual( [] );
		}
	);

	test(
		'SMOKE-02 Guide shows whether AI apps can connect',
		{ tag: [ '@smoke', '@wp-update' ] },
		async ( { admin, page } ) => {
			const errors = collectErrors( page );
			await admin.visitAdminPage( 'admin.php', 'page=publishio' );
			await expectLocalGuideStatus( page );

			// "Check again" opens the Status screen, which runs the full checks.
			await page.getByRole( 'link', { name: 'Check again' } ).click();
			await expect( page ).toHaveURL( new RegExp( STATUS_PAGE ) );
			await expectLocalStatus( page );

			expect( errors ).toEqual( [] );
		}
	);

	test(
		'SMOKE-02 Guide detail shows a copyable MCP server URL',
		{ tag: [ '@smoke', '@wp-update' ] },
		async ( { admin, page } ) => {
			await admin.visitAdminPage( 'admin.php', 'page=publishio' );
			await page
				.getByRole( 'button', { name: /Open Guide/ } )
				.first()
				.click();

			// The URL is shown in a read-only, labelled input inside CopyField.
			await expect(
				page.getByRole( 'textbox', { name: 'MCP Server URL' } )
			).toHaveValue( /\/wp-json\/mcp\/publishio$/ );
			// If the connection fails, the guide points to the Status screen.
			await expect(
				page.getByRole( 'link', { name: 'Open Status' } )
			).toHaveAttribute( 'href', new RegExp( `${ STATUS_PAGE }$` ) );
		}
	);

	test(
		'SMOKE-02 Connections screen renders (#126)',
		{ tag: [ '@multisite', '@wp-update' ] },
		async ( { admin, page } ) => {
			const errors = collectErrors( page );
			await admin.visitAdminPage(
				'admin.php',
				'page=publishio-connections'
			);

			const root = adminMain( page );
			await expect(
				root.getByRole( 'heading', { level: 1, name: 'Connections' } )
			).toBeVisible();
			// Either the empty state or the DataViews table must render: a blank root is the #126 failure.
			await expect(
				root
					.getByText( /No AI apps have connected yet/ )
					.or( root.getByRole( 'table' ) )
			).toBeVisible();

			expect( errors ).toEqual( [] );
		}
	);

	test(
		'SMOKE-02 Connections REST endpoint is admin-only',
		{ tag: [ '@smoke', '@wp-update' ] },
		async ( { requestUtils, anonApi } ) => {
			const data = await requestUtils.rest( {
				path: '/publishio/v1/connections',
			} );
			expect( Array.isArray( data.items ) ).toBe( true );
			expect( typeof data.total ).toBe( 'number' );

			const anon = await anonApi.get(
				'/wp-json/publishio/v1/connections'
			);
			expect( anon.status() ).toBe( 401 );
		}
	);

	test(
		'SMOKE-02 editors see the Guide but not Connections',
		{ tag: [ '@smoke', '@wp-update' ] },
		async ( { browser, requestUtils } ) => {
			const username = await ensureUser( requestUtils, 'editor' );
			const context = await loginBrowser( browser, username );
			const page = await context.newPage();

			await page.goto( '/wp-admin/admin.php?page=publishio' );
			await expect(
				page.getByRole( 'heading', { level: 1, name: 'Guide' } )
			).toBeVisible();

			await page.goto( '/wp-admin/admin.php?page=publishio-connections' );
			await expect(
				page.getByText( /not allowed to access this page/i )
			).toBeVisible();

			await context.close();
		}
	);
} );
