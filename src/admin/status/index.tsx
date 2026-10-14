/**
 * WordPress dependencies
 */
import { createRoot, StrictMode } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { StatusScreen } from './StatusScreen';

import '../../tailwind.scss';
import '../styles/styles.scss';
import './status.scss';

const root = document.querySelector( '#publishio-status-screen-root' );

if ( root ) {
	createRoot( root ).render(
		<StrictMode>
			<StatusScreen />
		</StrictMode>
	);
}
