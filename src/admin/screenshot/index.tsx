/**
 * WordPress dependencies
 */
import { StrictMode, createRoot } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { ScreenshotScreen } from './ScreenshotScreen';

import '../../tailwind.scss';
import '../styles/styles.scss';

const root = document.querySelector( '#rtpwai-screenshot-screen-root' );

if ( root ) {
	createRoot( root ).render(
		<StrictMode>
			<ScreenshotScreen />
		</StrictMode>
	);
}
