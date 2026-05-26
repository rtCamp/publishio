/**
 * WordPress dependencies
 */
import { createRoot, StrictMode } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { CredentialsScreen } from './CredentialsScreen';

import '../../tailwind.scss';
import '../styles/styles.scss';

const root = document.querySelector( '#rtpwai-credentials-screen-root' );

if ( root ) {
	createRoot( root ).render(
		<StrictMode>
			<CredentialsScreen />
		</StrictMode>
	);
}
