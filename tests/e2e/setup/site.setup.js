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
import { ALREADY, wpCli, wpCliQuietly } from '../utils/wp-cli';
import { ensureRunning } from '../utils/wp-env';

/**
 * Create the network's subsite if needed, with pretty permalinks and Publishio active. The main site keeps Publishio too: it answers discovery for a subdirectory site.
 * @param {{slug: string, url: string}} subsite The subsite's slug and URL.
 */
function prepareSubsite( subsite ) {
	if (
		! wpCli( 'site list --field=url' ).split( '\n' ).includes( subsite.url )
	) {
		wpCli( `site create --slug=${ subsite.slug }` );
	}

	wpCli( `rewrite structure '/%postname%/' --url=${ subsite.url }` );
	wpCli( `plugin activate publishio --url=${ subsite.url }` );
}

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

		if ( site.subsite ) {
			prepareSubsite( site.subsite );
		}
	} else {
		wpCliQuietly(
			'plugin deactivate publishio --uninstall',
			ALREADY.pluginMissing
		);
		wpCli( 'plugin delete publishio' );
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
