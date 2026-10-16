/**
 * External dependencies
 */
import { test } from '@playwright/test';
/**
 * Internal dependencies
 */
import { settings } from '../config/env';

/**
 * Skip the current test (or describe block) unless the run uses Twenty Twenty-Five, for tests
 * built on its named patterns. Other themes (E2E_THEME=ollie in the release watch) don't
 * register those patterns.
 */
export function requiresTwentyTwentyFive() {
	test.skip(
		settings.theme !== 'twentytwentyfive',
		`Uses Twenty Twenty-Five patterns; this run uses the "${ settings.theme }" theme.`
	);
}
