/**
 * INSTALL-09: minimum WordPress version is enforced (run with an older WP_ENV_CORE).
 */
/**
 * Internal dependencies
 */
import { test, expect } from '../../fixtures';
import { branchHeader, compareVersions, zipPath } from '../../utils/plugin-zip';
import { wpCli } from '../../utils/wp-cli';
import { evidence } from './helpers';

const escapeRegExp = ( value ) =>
	value.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );

test(
	'INSTALL-09 install is refused below the minimum WordPress version',
	{ tag: [ '@install', '@plugin-update', '@wp-update' ] },
	async ( { admin, page } ) => {
		const { requiresWp } = branchHeader();
		const wp = wpCli( 'core version' );
		test.skip(
			compareVersions( wp, requiresWp ) >= 0,
			`Needs WordPress below ${ requiresWp }; site runs ${ wp }.`
		);

		await admin.visitAdminPage( 'plugin-install.php', 'tab=upload' );
		await page.getByLabel( 'Plugin zip file' ).setInputFiles( zipPath() );
		await page.getByRole( 'button', { name: 'Install Now' } ).click();

		await expect(
			page.getByText( /Plugin installation failed/ )
		).toBeVisible();
		await expect(
			page.getByText(
				new RegExp( `requires.*${ escapeRegExp( requiresWp ) }` )
			)
		).toBeVisible();
		await evidence( page, 'INSTALL-09 refused' );

		expect(
			wpCli( 'plugin list --field=name' ).split( '\n' )
		).not.toContain( 'publishio' );
	}
);
