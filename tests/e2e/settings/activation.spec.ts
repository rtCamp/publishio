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

		const activateLink = page.locator( '#activate-publish-with-ai' );
		await expect( activateLink ).toBeVisible( { timeout: 10000 } );
		await activateLink.click();
		await page.waitForLoadState( 'domcontentloaded' );

		const deactivateLink = page.locator( '#deactivate-publish-with-ai', {
			hasText: 'Deactivate',
		} );
		await expect( deactivateLink ).toBeVisible( { timeout: 10000 } );
		await deactivateLink.click();
		await page.waitForLoadState( 'domcontentloaded' );

		const activateLinkAfterDeactivation = page.locator(
			'#activate-publish-with-ai',
			{
				hasText: 'Activate',
			}
		);
		await expect( activateLinkAfterDeactivation ).toBeVisible( {
			timeout: 10000,
		} );
	} );
} );
