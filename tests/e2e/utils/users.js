/**
 * External dependencies
 */
import { test } from '@playwright/test';
/**
 * WordPress dependencies
 */
export const PASSWORD = 'e2e-Password-123!';

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
		// RequestUtils rejects with the REST error object, not an Error. Single site reports an
		// existing user as existing_user_login; multisite as rest_invalid_param with user_name.
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
 * An API request context logged in as `username` through wp-login.php (cookie auth).
 * @param {import('@playwright/test').PlaywrightWorkerArgs['playwright']} playwright Playwright fixture.
 * @param {string}                                                        username   User login.
 * @param {string}                                                        password   User password.
 */
export async function loginApi( playwright, username, password = PASSWORD ) {
	const api = await playwright.request.newContext( {
		baseURL: test.info().project.use.baseURL,
		// wp-login.php needs its test cookie. Set it in the cookie jar: a Cookie header would
		// replace the jar on every request and drop the login cookies.
		storageState: {
			cookies: [
				{
					name: 'wordpress_test_cookie',
					value: 'WP%20Cookie%20check',
					domain: new URL( test.info().project.use.baseURL ).hostname,
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
 * A fresh browser context logged in as `username`.
 * @param {import('@playwright/test').Browser} browser  Browser fixture.
 * @param {string}                             username User login.
 * @param {string}                             password User password.
 */
export async function loginBrowser( browser, username, password = PASSWORD ) {
	const context = await browser.newContext( {
		baseURL: test.info().project.use.baseURL,
		storageState: { cookies: [], origins: [] },
	} );
	const page = await context.newPage();
	await page.goto( '/wp-login.php' );
	await page.getByLabel( 'Username or Email Address' ).fill( username );
	await page.getByLabel( 'Password', { exact: true } ).fill( password );
	await page.getByRole( 'button', { name: 'Log In' } ).click();
	await page.waitForURL( /wp-admin/ );
	await page.close();
	return context;
}
