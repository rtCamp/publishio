/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { EmptyState } from '../shared/EmptyState';

export function OtherAppsGuide() {
	return (
		<EmptyState
			message={ __(
				'Guide for other apps coming soon.',
				'rtcamp-publish-with-ai'
			) }
		/>
	);
}
