/**
 * External dependencies
 */
import { test } from '@playwright/test';
/**
 * Internal dependencies
 */
import { settings } from '../config/env';

/**
 * Skip unless the run uses Twenty Twenty-Five (its patterns are required).
 */
export function requiresTwentyTwentyFive() {
	test.skip(
		settings.theme !== 'twentytwentyfive',
		`Uses Twenty Twenty-Five patterns; this run uses the "${ settings.theme }" theme.`
	);
}
