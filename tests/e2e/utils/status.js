/**
 * Status screen (Publishio → Status) and the status line on the Guide.
 *
 * The checks run in the browser when the page opens. On the test sites (localhost) the expected
 * result is "Ready for Claude Code only": Public address warns that only this computer can reach
 * the site, and the other four checks pass.
 */
/**
 * Internal dependencies
 */
import { expect } from '../fixtures';

export const STATUS_PAGE = 'page=publishio-status';

/** Every check the Status screen runs, in display order. */
export const STATUS_CHECKS = [
	'Public address',
	'MCP server',
	'Connection details',
	'App verification',
	'Access after approval',
];

/** Verdict and Public address result for a site on localhost. */
export const LOCAL_VERDICT = 'Ready for Claude Code only';
export const LOCAL_ADDRESS_SUMMARY = 'Only this computer can reach it';

/**
 * Wait for the checks to finish, then assert the localhost result: one warning (Public address)
 * and the other four checks passed.
 * @param {import('@playwright/test').Page} page Page showing the Status screen.
 */
export async function expectLocalStatus( page ) {
	const main = page.getByRole( 'main' );
	await expect(
		main.getByRole( 'heading', { level: 1, name: 'Status' } )
	).toBeVisible();
	// "Checked just now from this browser, in 1.9 s" appears once every check has finished.
	await expect(
		main.getByText( /^Checked .* from this browser/ )
	).toBeVisible( { timeout: 30_000 } );
	await expect(
		main.getByRole( 'heading', { level: 2, name: LOCAL_VERDICT } )
	).toBeVisible();

	await expect(
		main.getByRole( 'heading', { level: 2, name: '1 problem' } )
	).toBeVisible();
	const address = main.getByRole( 'listitem' ).filter( {
		has: page.getByRole( 'heading', {
			level: 3,
			name: 'Public address',
		} ),
	} );
	await expect( address ).toContainText( 'Warning' );
	await expect( address ).toContainText( LOCAL_ADDRESS_SUMMARY );

	const passed = main.getByRole( 'button', {
		name: `Passed (${ STATUS_CHECKS.length - 1 })`,
	} );
	await passed.click();
	await expect( passed ).toHaveAttribute( 'aria-expanded', 'true' );
	for ( const name of STATUS_CHECKS.slice( 1 ) ) {
		await expect(
			main.getByRole( 'heading', { level: 3, name } ),
			`${ name } is listed as passed`
		).toBeVisible();
	}
}

/**
 * The status line on the Guide (Connect an AI app card): wait for the checks to finish, assert
 * the localhost result, and that "Check again" leads to the Status screen.
 * @param {import('@playwright/test').Page} page Page showing the Guide list.
 */
export async function expectLocalGuideStatus( page ) {
	const main = page.getByRole( 'main' );
	await expect(
		main.getByText( 'Checking whether AI apps can connect' )
	).toHaveCount( 0, { timeout: 30_000 } );
	await expect(
		main.getByText( LOCAL_VERDICT, { exact: true } )
	).toBeVisible();
	await expect(
		main.getByText( /This address only works on this computer/ )
	).toBeVisible();
	await expect(
		main.getByRole( 'link', { name: 'Check again' } )
	).toHaveAttribute( 'href', new RegExp( `${ STATUS_PAGE }$` ) );
}
