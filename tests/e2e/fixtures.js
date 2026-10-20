/**
 * Shared fixtures; specs import { test, expect } from here.
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
	// Override: the WordPress utils otherwise share one site URL and login file.
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
