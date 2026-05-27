/**
 * WordPress dependencies
 */
import { StrictMode, createRoot } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { SettingsScreen } from './SettingsScreen';

import '../../tailwind.scss';
import '../styles/styles.scss';

const root = document.querySelector( '#rtpwai-settings-screen-root' );

if ( root ) {
	createRoot( root ).render(
		<StrictMode>
			<SettingsScreen />
		</StrictMode>
	);
}
