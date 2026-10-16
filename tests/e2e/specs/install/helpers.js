/**
 * External dependencies
 */
import { test } from '@playwright/test';
/**
 * Internal dependencies
 */
import { expect } from '../../fixtures';

/**
 * Upload a plugin zip through Plugins → Add New.
 *
 * @param {import('@wordpress/e2e-test-utils-playwright').Admin} admin   Admin utils.
 * @param {import('@playwright/test').Page}                      page    Page to use.
 * @param {string}                                               file    Path of the file.
 * @param {boolean}                                              replace Replace an installed copy.
 */
export async function uploadZip( admin, page, file, replace = false ) {
	await admin.visitAdminPage( 'plugin-install.php', 'tab=upload' );
	await page.getByLabel( 'Plugin zip file' ).setInputFiles( file );
	await page.getByRole( 'button', { name: 'Install Now' } ).click();

	if ( replace ) {
		await page
			.getByRole( 'link', { name: /Replace current with uploaded/ } )
			.click();
		await expect(
			page.getByText( /Plugin updated successfully/ )
		).toBeVisible();
	} else {
		await expect(
			page.getByText( 'Plugin installed successfully.' )
		).toBeVisible();
	}
}

// Status checks probe these without credentials; their 401/400 console errors are expected.
const STATUS_PROBES = [
	/\/wp-json\/mcp\/publishio\b/,
	/\/wp-json\/publishio-oauth\/v1\/token\b/,
	/\/publishio-oauth\/authorize\b/,
];

/**
 * Collect JS errors and console errors from the page.
 *
 * @param {import('@playwright/test').Page} page Page to use.
 */
export function collectErrors( page ) {
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( `pageerror: ${ e.message }` ) );
	page.on( 'console', ( m ) => {
		if ( m.type() !== 'error' ) {
			return;
		}
		const refusedProbe =
			/^Failed to load resource: the server responded with a status of 40[01]\b/.test(
				m.text()
			) && STATUS_PROBES.some( ( re ) => re.test( m.location().url ) );
		if ( ! refusedProbe ) {
			errors.push( `console: ${ m.text() } (${ m.location().url })` );
		}
	} );
	return errors;
}

/**
 * Attach a named full-page screenshot to the report as evidence.
 *
 * @param {import('@playwright/test').Page} page Page to use.
 * @param {string}                          name Label of the screenshot in the report.
 */
export async function evidence( page, name ) {
	await test.info().attach( name, {
		body: await page.screenshot( { fullPage: true } ),
		contentType: 'image/png',
	} );
}

/**
 * Show plain text in the page for evidence screenshots.
 *
 * @param {import('@playwright/test').Page} page  Page to use.
 * @param {string}                          title Heading shown above the text.
 * @param {string}                          text  Text to show.
 */
export async function showText( page, title, text ) {
	const escape = ( value ) =>
		value.replace(
			/[&<>]/g,
			( c ) => ( { '&': '&amp;', '<': '&lt;', '>': '&gt;' } )[ c ]
		);
	await page.setContent(
		`<body style="font:14px monospace;padding:16px"><h3>${ escape(
			title
		) }</h3><pre>${ escape( text ) }</pre></body>`
	);
}

/**
 * The Publishio row on the Plugins screen.
 *
 * @param {import('@playwright/test').Page} page Page to use.
 * @return {import('@playwright/test').Locator} The row.
 */
export const pluginRow = ( page ) =>
	page.getByRole( 'row' ).filter( {
		has: page.getByRole( 'checkbox', { name: 'Select Publishio' } ),
	} );

/**
 * The admin content area, excluding the admin menu.
 *
 * @param {import('@playwright/test').Page} page Page to use.
 * @return {import('@playwright/test').Locator} The main region.
 */
export const adminMain = ( page ) => page.getByRole( 'main' );

/**
 * The admin menu navigation.
 *
 * @param {import('@playwright/test').Page} page Page to use.
 * @return {import('@playwright/test').Locator} The admin menu navigation.
 */
export const adminMenu = ( page ) =>
	page.getByRole( 'navigation', { name: 'Main menu' } );
