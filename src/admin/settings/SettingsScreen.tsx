/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { AdminHeader } from '../shared/AdminHeader';
import { ScreenNotices } from '../shared/ScreenNotices';

const NOTICES_CONTEXT = 'rtpwai-settings';

export function SettingsScreen() {
	return (
		<>
			<AdminHeader
				title={ __( 'Settings', 'rtcamp-publish-with-ai' ) }
				description={ __(
					'Configure plugin behaviour and integrations.',
					'rtcamp-publish-with-ai'
				) }
			/>

			<main className="p-6 flex flex-col gap-6">
			</main>

			<ScreenNotices context={ NOTICES_CONTEXT } />
		</>
	);
}
