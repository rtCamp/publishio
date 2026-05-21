/**
 * WordPress dependencies
 */
import { expect, test } from '@wordpress/e2e-test-utils-playwright';

test.describe( 'plugin activation', () => {
	test( 'should activate and deactivate the plugin', async ( {
		admin,
		page,
	} ) => {
		await admin.visitAdminPage( '/plugins.php' );

		const pluginRow = page.locator(
			'tr[data-plugin="rtcamp-publish-with-ai/rtcamp-publish-with-ai.php"]'
		);
		await expect( pluginRow ).toBeVisible();

		const activateLink = pluginRow.locator( 'a', { hasText: 'Activate' } );
		await activateLink.click();
		await page.waitForLoadState( 'domcontentloaded' );

		await expect(
			pluginRow.locator( 'a', { hasText: 'Deactivate' } )
		).toBeVisible( { timeout: 10000 } );

		const deactivateLink = pluginRow.locator( 'a', {
			hasText: 'Deactivate',
		} );
		await deactivateLink.click();
		await page.waitForLoadState( 'domcontentloaded' );

		await expect(
			pluginRow.locator( 'a', { hasText: 'Activate' } )
		).toBeVisible( { timeout: 10000 } );
	} );
} );
