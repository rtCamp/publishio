/**
 * External dependencies
 */
import { expect, test } from '@playwright/test';
/**
 * Internal dependencies
 */
import { wpCliQuietly } from './wp-cli';

export const PASSWORD = 'e2e-Password-123!';

/**
 * Create (or reuse) a user on one site of a network, with WP-CLI.
 *
 * @param {string} login   User login: letters and digits only, as multisite requires.
 * @param {string} role    WordPress role on that site.
 * @param {string} siteUrl The site's URL.
 * @return {string} The login.
 */
export function ensureSiteUser( login, role, siteUrl ) {
	wpCliQuietly(
		`user create ${ login } ${ login }@example.test --role=${ role } --user_pass=${ PASSWORD } --url=${ siteUrl }`,
		/already registered/
	);
	return login;
}

/**
 * Create (or reuse) a user with the given role.
 * @param {import('@wordpress/e2e-test-utils-playwright').RequestUtils} requestUtils WordPress request utils.
 * @param {string}                                                      role         WordPress role.
 */
export async function ensureUser( requestUtils, role ) {
	// Letters and digits only: multisite rejects underscores in usernames.
	const username = `e2e${ role }`;
	try {
		await requestUtils.createUser( {
			username,
			email: `${ username }@example.test`,
			firstName: 'E2E',
			lastName: role,
			password: PASSWORD,
			roles: [ role ],
		} );
	} catch ( e ) {
		// Single site: existing_user_login; multisite: user_name "already exists".
		const error = JSON.stringify( e );
		const exists =
			error.includes( 'existing_user_login' ) ||
			( error.includes( '"user_name"' ) &&
				error.includes( 'already exists' ) );
		if ( ! exists ) {
			throw e;
		}
	}
	return username;
}

/**
 * API request context logged in via wp-login.php.
 * @param {import('@playwright/test').PlaywrightWorkerArgs['playwright']} playwright Playwright fixture.
 * @param {string}                                                        username   User login.
 * @param {string}                                                        password   User password.
 * @param {string}                                                        baseURL    Site to log in to; the project's when omitted.
 */
export async function loginApi(
	playwright,
	username,
	password = PASSWORD,
	baseURL = test.info().project.use.baseURL
) {
	const api = await playwright.request.newContext( {
		baseURL,
		// Jar, not a Cookie header: the header would drop the login cookies.
		storageState: {
			cookies: [
				{
					name: 'wordpress_test_cookie',
					value: 'WP%20Cookie%20check',
					domain: new URL( baseURL ).hostname,
					path: '/',
					expires: -1,
					httpOnly: false,
					secure: false,
					sameSite: 'Lax',
				},
			],
			origins: [],
		},
	} );
	const res = await api.post( '/wp-login.php', {
		form: {
			log: username,
			pwd: password,
			'wp-submit': 'Log In',
			testcookie: '1',
		},
		maxRedirects: 0,
	} );
	if ( res.status() !== 302 ) {
		throw new Error(
			`Login failed for ${ username }: HTTP ${ res.status() }`
		);
	}
	return api;
}

/**
 * Fresh browser context logged in as `username`.
 * @param {import('@playwright/test').Browser} browser  Browser fixture.
 * @param {string}                             username User login.
 * @param {string}                             password User password.
 * @param {string}                             baseURL  Site to log in to; the project's when omitted.
 */
export async function loginBrowser(
	browser,
	username,
	password = PASSWORD,
	baseURL = test.info().project.use.baseURL
) {
	const context = await browser.newContext( {
		baseURL,
		storageState: { cookies: [], origins: [] },
	} );
	const page = await context.newPage();
	await page.goto( '/wp-login.php' );
	const user = page.getByLabel( 'Username or Email Address' );
	const pass = page.getByLabel( 'Password', { exact: true } );
	// wp-login.php focuses and selects the username field 200 ms after load (wp_attempt_focus).
	// Typing before then can land the password in the username field, so wait for it.
	await expect( user ).toBeFocused();
	await user.fill( username );
	await pass.fill( password );
	await expect( user ).toHaveValue( username );
	await expect( pass ).toHaveValue( password );
	await page.getByRole( 'button', { name: 'Log In' } ).click();
	await page.waitForURL( /wp-admin/ );
	await page.close();
	return context;
}
