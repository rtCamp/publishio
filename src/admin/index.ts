/**
 * WordPress dependencies
 */
import domReady from '@wordpress/dom-ready';

/**
 * Internal dependencies
 */
import './styles/styles.module.scss';

/**
 * Example `admin` entrypoint.
 *
 * In real projects, you will likely have multiple entrypoints for different admin screens, and not a single entrypoint.
 */
domReady( () => {
	// eslint-disable-next-line no-console -- @todo Remove this console log and add your own code here.
	console.log( 'Remove or replace this admin script before shipping!' );
} );
