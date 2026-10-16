/**
 * Setup project "setup-<site>": starts the site's wp-env instance if it is not running, then puts
 * it into its starting state. Playwright runs it only when a selected project depends on that
 * site; the matching teardown project (site.teardown.js) stops it again after those projects.
 * - "mounted" sites: plugin active from the repo, chosen theme active, content reset.
 * - "zip" sites: plugin NOT installed, so tests install it the way a customer does.
 */
/**
 * External dependencies
 */
import fs from 'node:fs';
import { test as setup } from '@playwright/test';
/**
 * WordPress dependencies
 */
import { RequestUtils } from '@wordpress/e2e-test-utils-playwright';
/**
 * Internal dependencies
 */
import { currentSite, pointWordPressUtilsAt, settings } from '../config/env';
import { wpCli } from '../utils/wp-cli';
import { ensureRunning } from '../utils/wp-env';

const quietly = ( cmd ) => {
	try {
		wpCli( cmd );
	} catch {
		// Expected when the plugin or theme is already in the target state.
	}
};

setup( 'prepare site', async ( { playwright } ) => {
	const site = currentSite();
	setup.info().annotations.push( {
		type: 'site',
		description: `${ site.name }: ${ site.baseURL } (WordPress ${ site.wpLabel })`,
	} );

	if ( site.plugin === 'zip' && ! fs.existsSync( settings.zip ) ) {
		throw new Error(
			`Plugin zip not found at ${ settings.zip }. Run bin/get-plugin-zip.sh <branch> (or --build) first.`
		);
	}

	if ( await ensureRunning( site ) ) {
		setup.info().annotations.push( {
			type: 'wp-env',
			description: `Started ${ site.name }; teardown will stop it.`,
		} );
	}

	// WP-CLI first: it is slow (docker exec), and Apache drops idle keep-alive sockets after 5s,
	// which an open Playwright request context would then reuse and fail with ECONNRESET.
	// Pretty permalinks: required for /.well-known/* and /publishio-oauth/authorize.
	wpCli( `rewrite structure '/%postname%/' --hard` );

	if (
		! wpCli( 'theme list --field=name' )
			.split( '\n' )
			.includes( settings.theme )
	) {
		wpCli( `theme install ${ settings.theme }` );
	}
	wpCli( `theme activate ${ settings.theme }` );

	if ( site.plugin === 'mounted' ) {
		wpCli( 'plugin activate publishio' );
		wpCli( 'rewrite flush --hard' );
	} else {
		quietly( 'plugin deactivate publishio --uninstall' );
		quietly( 'plugin delete publishio' );
	}

	pointWordPressUtilsAt( site.baseURL );
	const requestContext = await playwright.request.newContext( {
		baseURL: site.baseURL,
	} );
	const requestUtils = new RequestUtils( requestContext, {
		storageStatePath: site.storageState,
	} );
	await requestUtils.setupRest();

	if ( site.plugin === 'mounted' ) {
		await Promise.all( [
			requestUtils.deleteAllPosts(),
			requestUtils.deleteAllPages(),
			requestUtils.deleteAllBlocks(),
		] );
	}

	await requestContext.dispose();
} );
