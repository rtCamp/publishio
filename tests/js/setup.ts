/**
 * External dependencies
 */
import '@testing-library/jest-dom';

jest.mock( '@wordpress/i18n', () => ( {
	__: jest.fn( ( text: string ) => text ),
} ) );

jest.mock( '@wordpress/element', () => {
	return jest.requireActual( 'react' );
} );

beforeEach( () => {
	jest.clearAllMocks();
} );
