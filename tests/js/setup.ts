/**
 * External dependencies
 */
import '@testing-library/jest-dom';

const mockUseBlockProps: jest.Mock & { save: jest.Mock } = Object.assign(
	jest.fn( ( props = {} ) => ( {
		className: 'wp-block',
		...props,
	} ) ),
	{
		save: jest.fn( ( props = {} ) => ( {
			className: 'wp-block',
			...props,
		} ) ),
	}
);

jest.mock( '@wordpress/block-editor', () => ( {
	useBlockProps: mockUseBlockProps,
} ) );

jest.mock( '@wordpress/i18n', () => ( {
	__: jest.fn( ( text: string ) => text ),
} ) );

jest.mock( '@wordpress/element', () => {
	return jest.requireActual( 'react' );
} );

beforeEach( () => {
	jest.clearAllMocks();
} );
