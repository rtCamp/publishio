/**
 * Shared fixtures. Specs import { test, expect } from here instead of
 * `@wordpress/e2e-test-utils-playwright` directly.
 *
 * - anonApi:  HTTP client with no cookies (what an AI client looks like to WordPress).
 * - adminApi: HTTP client logged in as admin via the stored session.
 * - connection: one OAuth connection per worker, reused by tests that only need a token.
 * - mcp:      an initialized MCP client authenticated with that token.
 *
 * Every fixture uses the site of the test's project (baseURL and storageState from the config).
 */
/**
 * WordPress dependencies
 */
import {
	test as base,
	expect,
	RequestUtils,
} from '@wordpress/e2e-test-utils-playwright';
/**
 * Internal dependencies
 */
import { connect } from './utils/oauth';
import { McpClient } from './utils/mcp';
import { pointWordPressUtilsAt } from './config/env';

export const test = base.extend( {
	// The WordPress utils keep one login file and one site URL for every site; use the project's.
	requestUtils: [
		async ( {}, use, workerInfo ) => {
			pointWordPressUtilsAt( workerInfo.project.use.baseURL );
			await use(
				await RequestUtils.setup( {
					baseURL: workerInfo.project.use.baseURL,
					storageStatePath: workerInfo.project.use.storageState,
				} )
			);
		},
		// Must repeat the original's options.
		{ scope: 'worker', auto: true },
	],

	anonApi: [
		async ( { playwright }, use, workerInfo ) => {
			const api = await playwright.request.newContext( {
				baseURL: workerInfo.project.use.baseURL,
				storageState: { cookies: [], origins: [] },
			} );
			await use( api );
			await api.dispose();
		},
		{ scope: 'worker' },
	],

	adminApi: [
		async ( { playwright }, use, workerInfo ) => {
			const api = await playwright.request.newContext( {
				baseURL: workerInfo.project.use.baseURL,
				storageState: workerInfo.project.use.storageState,
			} );
			await use( api );
			await api.dispose();
		},
		{ scope: 'worker' },
	],

	connection: [
		async ( { anonApi, adminApi }, use ) => {
			await use( await connect( anonApi, adminApi ) );
		},
		{ scope: 'worker' },
	],

	mcp: async ( { anonApi, connection }, use ) => {
		const client = new McpClient(
			anonApi,
			connection.discovery.resource,
			connection.tokens.access_token
		);
		await client.initialize();
		await use( client );
	},
} );

export { expect };
