/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { AdminHeader } from '../shared/AdminHeader';
import { ScreenNotices } from '../shared/ScreenNotices';
import { ScreenshotSection } from './sections/ScreenshotSection';

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
				<section className="bg-white rounded-lg border border-gray-200 p-6 max-w-2xl">
					<h2 className="mt-0 mb-4 text-sm font-semibold text-gray-900">
						{ __( 'Screenshots', 'rtcamp-publish-with-ai' ) }
					</h2>

					<ScreenshotSection noticesContext={ NOTICES_CONTEXT } />
				</section>
			</main>

			<ScreenNotices context={ NOTICES_CONTEXT } />
		</>
	);
}
