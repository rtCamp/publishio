/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { EmptyState } from '../shared/EmptyState';

export function OpenAIGuide() {
	return (
		<EmptyState
			message={ __( 'Guide for OpenAI coming soon.', 'publishio' ) }
		/>
	);
}
