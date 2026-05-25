/**
 * WordPress dependencies
 */
import { createRoot, StrictMode } from '@wordpress/element'; // eslint-disable-line import/no-extraneous-dependencies

/**
 * Internal dependencies
 */
import { App } from './App';

import '../tailwind.scss';
import './styles/styles.scss';

const rootElement = document.querySelector( '#rtpwai-admin-screen-root' );

if ( rootElement ) {
	createRoot( rootElement ).render(
		<StrictMode>
			<App />
		</StrictMode>
	);
}
