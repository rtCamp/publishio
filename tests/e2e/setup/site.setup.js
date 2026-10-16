/**
 * Starts a site if needed and resets it to its starting state.
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
		// Already in the target state.
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

	// WP-CLI before any request context: stale keep-alive sockets cause ECONNRESET.
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
