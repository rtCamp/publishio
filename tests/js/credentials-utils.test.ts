/**
 * Tests for credential utility functions.
 */
/**
 * External dependencies
 */
import {
	getInvalidUris,
	isValidHttpsUrl,
	getApiErrorMessage,
} from '@/admin/credentials/utils';

describe( 'getInvalidUris', () => {
	it( 'returns empty array when all URIs are valid https', () => {
		expect( getInvalidUris( [ 'https://example.com/callback' ] ) ).toEqual(
			[]
		);
	} );

	it( 'flags http non-localhost URIs as invalid', () => {
		expect( getInvalidUris( [ 'http://example.com/callback' ] ) ).toEqual( [
			'http://example.com/callback',
		] );
	} );

	it( 'allows http://localhost', () => {
		expect(
			getInvalidUris( [ 'http://localhost:8888/callback' ] )
		).toEqual( [] );
	} );

	it( 'allows http://127.0.0.1', () => {
		expect( getInvalidUris( [ 'http://127.0.0.1/callback' ] ) ).toEqual(
			[]
		);
	} );

	// IPv6 loopback hostname retains brackets in this environment,
	// so `::1` literal check does not match. This is a known limitation.
	it( 'flags IPv6 loopback URIs as invalid (known limitation)', () => {
		expect( getInvalidUris( [ 'http://[::1]/callback' ] ) ).toEqual( [
			'http://[::1]/callback',
		] );
	} );

	it( 'flags URIs with hash fragments as invalid', () => {
		expect(
			getInvalidUris( [ 'https://example.com/callback#fragment' ] )
		).toEqual( [ 'https://example.com/callback#fragment' ] );
	} );

	it( 'flags malformed URIs as invalid', () => {
		expect( getInvalidUris( [ 'not-a-uri' ] ) ).toEqual( [ 'not-a-uri' ] );
	} );

	it( 'returns only the invalid URIs from a mixed list', () => {
		expect(
			getInvalidUris( [
				'https://example.com/callback',
				'http://bad.com/callback',
				'garbage',
			] )
		).toEqual( [ 'http://bad.com/callback', 'garbage' ] );
	} );

	it( 'returns empty array for empty input', () => {
		expect( getInvalidUris( [] ) ).toEqual( [] );
	} );
} );

describe( 'isValidHttpsUrl', () => {
	it( 'returns true for empty string', () => {
		expect( isValidHttpsUrl( '' ) ).toBe( true );
	} );

	it( 'returns true for whitespace-only string', () => {
		expect( isValidHttpsUrl( '   ' ) ).toBe( true );
	} );

	it( 'returns true for valid https URL', () => {
		expect( isValidHttpsUrl( 'https://example.com' ) ).toBe( true );
	} );

	it( 'returns false for http URL', () => {
		expect( isValidHttpsUrl( 'http://example.com' ) ).toBe( false );
	} );

	it( 'returns false for invalid URL', () => {
		expect( isValidHttpsUrl( 'not-a-url' ) ).toBe( false );
	} );

	it( 'trims whitespace before validating', () => {
		expect( isValidHttpsUrl( '  https://example.com  ' ) ).toBe( true );
	} );
} );

describe( 'getApiErrorMessage', () => {
	it( 'returns message string from API error object', () => {
		expect(
			getApiErrorMessage( {
				code: 'error_code',
				message: 'Something went wrong',
			} )
		).toBe( 'Something went wrong' );
	} );

	it( 'returns null when message is empty string', () => {
		expect( getApiErrorMessage( { message: '' } ) ).toBeNull();
	} );

	it( 'returns null when message is not a string', () => {
		expect( getApiErrorMessage( { message: 123 } ) ).toBeNull();
	} );

	it( 'returns null for null input', () => {
		expect( getApiErrorMessage( null ) ).toBeNull();
	} );

	it( 'returns null for undefined input', () => {
		expect( getApiErrorMessage( undefined ) ).toBeNull();
	} );

	it( 'returns null for non-object input', () => {
		expect( getApiErrorMessage( 'string error' ) ).toBeNull();
	} );

	it( 'returns null for object without message property', () => {
		expect( getApiErrorMessage( { code: 'error' } ) ).toBeNull();
	} );
} );
