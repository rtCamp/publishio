/**
 * Stops a site if its setup started it.
 */
/**
 * External dependencies
 */
import { test as teardown } from '@playwright/test';
/**
 * Internal dependencies
 */
import { currentSite } from '../config/env';
import { stopIfStarted } from '../utils/wp-env';

teardown( 'stop site', () => {
	const site = currentSite();
	const stopped = stopIfStarted( site );
	teardown.info().annotations.push( {
		type: 'wp-env',
		description: stopped
			? `Stopped ${ site.name }.`
			: `Left ${ site.name } running (not started by this run, or E2E_KEEP_SITES=1).`,
	} );
} );
