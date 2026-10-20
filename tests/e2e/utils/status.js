/**
 * Status screen and Guide status line helpers.
 */
/**
 * Internal dependencies
 */
import { test, expect } from '../fixtures';

export const STATUS_PAGE = 'page=publishio-status';

/** In display order. */
export const STATUS_CHECKS = [
	'Public address',
	'MCP server',
	'Connection details',
	'App verification',
	'Access after approval',
];

/** Status page copy the tests assert on; the one place to update when the UI wording changes. */
export const STATUS_TEXT = {
	localVerdict: 'Ready for Claude Code only',
	localAddressSummary: 'Only this computer can reach it',
	localAddressMessage: 'This address only works on this computer',
	localAddressFix: 'put the site on a public https:// address',
	checking: 'Checking whether AI apps can connect',
	oneProblem: '1 problem',
	twoProblems: '2 problems',
	howToFix: 'How to fix',
	technicalDetails: 'Technical details',
	caveats: "What these checks can't see",
	reportTitle: 'Status report',
	copyReport: 'Copy report',
	copied: 'Copied!',
	couldNotVerify: "Couldn't verify",
	blockedOutgoing: 'WordPress is set to block outgoing connections.',
	appExpected: '200 JSON, accepted',
	detailsUnreadable: "AI apps can't read them",
	blockedDiscovery:
		'Your web server blocked the connection details before WordPress saw them.',
	discoveryFixPath: '/.well-known/oauth-',
};

/**
 * @param {import('@playwright/test').Page} page Page showing the Status screen.
 */
export async function waitForChecks( page ) {
	await expect(
		page.getByRole( 'main' ).getByText( /^Checked .* from this browser/ )
	).toBeVisible( { timeout: 30_000 } );
}

/**
 * Retry up to twice: App verification has a 5 s network limit and is occasionally flaky.
 * @param {import('@playwright/test').Page} page Page showing the Status screen.
 */
async function retryAppVerification( page ) {
	const main = page.getByRole( 'main' );
	const failed = main
		.getByRole( 'listitem' )
		.filter( {
			has: page.getByRole( 'heading', {
				level: 3,
				name: 'App verification',
			} ),
		} )
		.filter( { hasText: STATUS_TEXT.couldNotVerify } );
	let attempt = 0;
	for ( ; attempt < 2 && ( await failed.count() ); attempt++ ) {
		const again = page.getByRole( 'button', { name: 'Check again' } );
		await again.click();
		await expect( again ).toHaveAttribute( 'aria-disabled', 'true' );
		await expect( again ).not.toHaveAttribute( 'aria-disabled', 'true', {
			timeout: 30_000,
		} );
		await waitForChecks( page );
	}
	if ( attempt ) {
		// Not silent: the report shows every test where App verification needed a retry.
		test.info().annotations.push( {
			type: 'app verification retried',
			description: `${ attempt } retry(s); last result: ${
				( await failed.count() ) ? 'still failing' : 'passed'
			}`,
		} );
	}
}

/**
 * Assert the localhost result: Public address warns, the rest pass.
 * @param {import('@playwright/test').Page} page Page showing the Status screen.
 */
export async function expectLocalStatus( page ) {
	const main = page.getByRole( 'main' );
	await expect(
		main.getByRole( 'heading', { level: 1, name: 'Status' } )
	).toBeVisible();
	await waitForChecks( page );
	await retryAppVerification( page );
	await expect(
		main.getByRole( 'heading', {
			level: 2,
			name: STATUS_TEXT.localVerdict,
		} )
	).toBeVisible();

	await expect(
		main.getByRole( 'heading', { level: 2, name: STATUS_TEXT.oneProblem } )
	).toBeVisible();
	const address = main.getByRole( 'listitem' ).filter( {
		has: page.getByRole( 'heading', {
			level: 3,
			name: 'Public address',
		} ),
	} );
	await expect( address ).toContainText( 'Warning' );
	await expect( address ).toContainText( STATUS_TEXT.localAddressSummary );

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
 * Assert the localhost result on the Guide status line.
 * @param {import('@playwright/test').Page} page Page showing the Guide list.
 */
export async function expectLocalGuideStatus( page ) {
	const main = page.getByRole( 'main' );
	await expect( main.getByText( STATUS_TEXT.checking ) ).toHaveCount( 0, {
		timeout: 30_000,
	} );
	await expect(
		main.getByText( STATUS_TEXT.localVerdict, { exact: true } )
	).toBeVisible();
	await expect(
		main.getByText( STATUS_TEXT.localAddressMessage )
	).toBeVisible();
	await expect(
		main.getByRole( 'link', { name: 'Check again' } )
	).toHaveAttribute( 'href', new RegExp( `${ STATUS_PAGE }$` ) );
}
