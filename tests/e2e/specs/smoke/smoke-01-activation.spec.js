/**
 * SMOKE-01: plugin activation.
 */
/**
 * External dependencies
 */
import fs from 'node:fs';
/**
 * Internal dependencies
 */
import { test, expect } from '../../fixtures';
import { markDebugLog } from '../../utils/debug-log';
import { wpCli } from '../../utils/wp-cli';
import { branchHeader } from '../../utils/plugin-zip';
import { adminMenu, pluginRow } from '../install/helpers';

// DB_VERSION from inc/Main.php.
const dbVersion = () =>
	Number(
		fs
			.readFileSync( 'inc/Main.php', 'utf8' )
			.match( /const DB_VERSION\s*=\s*(\d+)/ )?.[ 1 ]
	);

test.describe( 'Activation', { tag: '@plugin-update' }, () => {
	test.describe.configure( { mode: 'serial' } );

	test.afterAll( async ( { requestUtils } ) => {
		await requestUtils.activatePlugin( 'publishio' );
	} );

	test(
		'SMOKE-01.1 activates from the Plugins screen without errors',
		{ tag: [ '@multisite', '@wp-update' ] },
		async ( { admin, page, requestUtils } ) => {
			await requestUtils.deactivatePlugin( 'publishio' );
			const newLogProblems = markDebugLog();
			const pageErrors = [];
			page.on( 'pageerror', ( e ) => pageErrors.push( e.message ) );

			await admin.visitAdminPage( 'plugins.php' );
			const row = pluginRow( page );
			await row.getByRole( 'link', { name: /^Activate/ } ).click();

			await expect( page.getByText( 'Plugin activated.' ) ).toBeVisible();
			await expect(
				page.getByText( /could not be activated|fatal error/i )
			).toHaveCount( 0 );
			await expect(
				row.getByRole( 'link', { name: 'Settings' } )
			).toBeVisible();
			await expect(
				adminMenu( page ).getByRole( 'link', {
					name: 'Publishio',
					exact: true,
				} )
			).toBeVisible();

			expect(
				newLogProblems(),
				'PHP errors from Publishio in debug.log'
			).toEqual( [] );
			expect( pageErrors, 'JS errors on the Plugins screen' ).toEqual(
				[]
			);
		}
	);

	test(
		'SMOKE-01.2 activation flushes OAuth rewrite rules',
		{ tag: [ '@multisite', '@wp-update' ] },
		async ( { anonApi } ) => {
			for ( const p of [
				'/.well-known/oauth-authorization-server',
				'/.well-known/oauth-protected-resource',
			] ) {
				const res = await anonApi.get( p );
				expect( res.status(), p ).toBe( 200 );
				expect( res.headers()[ 'content-type' ] ).toContain(
					'application/json'
				);
			}
		}
	);

	test(
		'SMOKE-01.3 stores the current schema version',
		{ tag: '@smoke' },
		async () => {
			expect( wpCli( 'option get publishio_version' ) ).toBe(
				branchHeader().version
			);
			expect( dbVersion(), 'DB_VERSION in inc/Main.php' ).toBeGreaterThan(
				0
			);
			expect( Number( wpCli( 'option get publishio_db_version' ) ) ).toBe(
				dbVersion()
			);
		}
	);

	test(
		'SMOKE-01.4 deactivation does not leave the site broken',
		{ tag: [ '@multisite', '@wp-update' ] },
		async ( { requestUtils, anonApi } ) => {
			const newLogProblems = markDebugLog();
			await requestUtils.deactivatePlugin( 'publishio' );

			const home = await anonApi.get( '/' );
			expect( home.status() ).toBe( 200 );
			const mcp = await anonApi.post( '/wp-json/mcp/publishio', {
				data: {},
			} );
			expect(
				mcp.status(),
				'MCP route should be gone when inactive'
			).toBe( 404 );

			expect( newLogProblems() ).toEqual( [] );
			await requestUtils.activatePlugin( 'publishio' );
		}
	);
} );
