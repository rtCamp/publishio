/**
 * WordPress dependencies
 */
import { createRoot, StrictMode } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { GuideScreen } from './GuideScreen';

import '../../tailwind.scss';
import '../styles/styles.scss';

const root = document.querySelector( '#rtpwai-admin-screen-root' );

if ( root ) {
	createRoot( root ).render(
		<StrictMode>
			<GuideScreen />
		</StrictMode>
	);
}
