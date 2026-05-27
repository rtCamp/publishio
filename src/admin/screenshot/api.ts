/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import type { ScreenshotSettings, UpdateScreenshotPayload } from './types';

const REST_PATH = '/rtpwai/v1/screenshot-settings';

export const screenshotApi = {
	get: (): Promise< ScreenshotSettings > => apiFetch( { path: REST_PATH } ),

	update: ( data: UpdateScreenshotPayload ): Promise< ScreenshotSettings > =>
		apiFetch( { path: REST_PATH, method: 'PUT', data } ),
};
