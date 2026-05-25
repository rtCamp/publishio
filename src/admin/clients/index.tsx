/**
 * WordPress dependencies
 */
import { createRoot, StrictMode } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { ClientsScreen } from './ClientsScreen';

import '../../tailwind.scss';
import '../styles/styles.scss';

const root = document.querySelector( '#rtpwai-clients-screen-root' );

if ( root ) {
	createRoot( root ).render(
		<StrictMode>
			<ClientsScreen />
		</StrictMode>
	);
}
