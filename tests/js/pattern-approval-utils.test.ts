/**
 * Tests for pattern-approval utility functions.
 */
/**
 * External dependencies
 */
import { formatPosition } from '@/apps/pattern-approval/utils';

describe( 'formatPosition', () => {
	it( 'returns "the selected location" for undefined', () => {
		expect( formatPosition( undefined ) ).toBe( 'the selected location' );
	} );

	it( 'returns "the end of the page" for -1', () => {
		expect( formatPosition( -1 ) ).toBe( 'the end of the page' );
	} );

	it( 'returns "the top of the page" for 0', () => {
		expect( formatPosition( 0 ) ).toBe( 'the top of the page' );
	} );

	it( 'formats 1st position', () => {
		expect( formatPosition( 1 ) ).toBe( 'after the 1st block' );
	} );

	it( 'formats 2nd position', () => {
		expect( formatPosition( 2 ) ).toBe( 'after the 2nd block' );
	} );

	it( 'formats 3rd position', () => {
		expect( formatPosition( 3 ) ).toBe( 'after the 3rd block' );
	} );

	it( 'formats 4th position with th suffix', () => {
		expect( formatPosition( 4 ) ).toBe( 'after the 4th block' );
	} );

	it( 'formats 11th correctly (special case)', () => {
		expect( formatPosition( 11 ) ).toBe( 'after the 11th block' );
	} );

	it( 'formats 12th correctly (special case)', () => {
		expect( formatPosition( 12 ) ).toBe( 'after the 12th block' );
	} );

	it( 'formats 13th correctly (special case)', () => {
		expect( formatPosition( 13 ) ).toBe( 'after the 13th block' );
	} );

	it( 'formats 21st correctly', () => {
		expect( formatPosition( 21 ) ).toBe( 'after the 21st block' );
	} );

	it( 'formats 22nd correctly', () => {
		expect( formatPosition( 22 ) ).toBe( 'after the 22nd block' );
	} );

	it( 'formats 23rd correctly', () => {
		expect( formatPosition( 23 ) ).toBe( 'after the 23rd block' );
	} );

	it( 'formats 111th correctly (teen exception)', () => {
		expect( formatPosition( 111 ) ).toBe( 'after the 111th block' );
	} );

	it( 'formats 112th correctly (teen exception)', () => {
		expect( formatPosition( 112 ) ).toBe( 'after the 112th block' );
	} );

	it( 'formats 113th correctly (teen exception)', () => {
		expect( formatPosition( 113 ) ).toBe( 'after the 113th block' );
	} );
} );
