/**
 * SMOKE-02: every admin screen renders (#126).
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

test.describe( 'Admin screens', { tag: [ '@smoke', '@plugin-update' ] }, () => {
	test(
		'SMOKE-02.1 Guide screen renders',
		{ tag: '@wp-update' },
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
		'SMOKE-02.2 Guide shows whether AI apps can connect',
		{ tag: '@wp-update' },
		async ( { admin, page } ) => {
			const errors = collectErrors( page );
			await admin.visitAdminPage( 'admin.php', 'page=publishio' );
			await expectLocalGuideStatus( page );

			await page.getByRole( 'link', { name: 'Check again' } ).click();
			await expect( page ).toHaveURL( new RegExp( STATUS_PAGE ) );
			await expectLocalStatus( page );

			expect( errors ).toEqual( [] );
		}
	);

	test(
		'SMOKE-02.3 Guide detail shows a copyable MCP server URL',
		{ tag: '@wp-update' },
		async ( { admin, page } ) => {
			await admin.visitAdminPage( 'admin.php', 'page=publishio' );
			await page
				.getByRole( 'button', { name: /Open Guide/ } )
				.first()
				.click();

			await expect(
				page.getByRole( 'textbox', { name: 'MCP Server URL' } )
			).toHaveValue( /\/wp-json\/mcp\/publishio$/ );
			await expect(
				page.getByRole( 'link', { name: 'Open Status' } )
			).toHaveAttribute( 'href', new RegExp( `${ STATUS_PAGE }$` ) );
		}
	);

	test(
		'SMOKE-02.4 Connections screen renders (#126)',
		{ tag: '@wp-update' },
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
			await expect(
				root
					.getByText( /No AI apps have connected yet/ )
					.or( root.getByRole( 'table' ) )
			).toBeVisible();

			expect( errors ).toEqual( [] );
		}
	);

	test(
		'SMOKE-02.5 Connections REST endpoint is admin-only',
		{ tag: '@wp-update' },
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
		'SMOKE-02.6 editors see the Guide but not Connections',
		{ tag: '@wp-update' },
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
