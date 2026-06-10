/**
 * WordPress dependencies
 */
import { createRoot, StrictMode } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { ConnectionsScreen } from './ConnectionsScreen';

import '../../tailwind.scss';
import '../styles/styles.scss';

const root = document.querySelector( '#publishio-connections-screen-root' );

if ( root ) {
	createRoot( root ).render(
		<StrictMode>
			<ConnectionsScreen />
		</StrictMode>
	);
}
