/**
 * Tests for pinning viewport-height lengths in preview documents.
 */
/**
 * External dependencies
 */
import {
	pinViewportLengths,
	pinViewportUnits,
} from '@/apps/preview/viewport-units';

const WIDTH = 1300;
const HEIGHT = 800;

describe( 'pinViewportLengths', () => {
	it.each( [
		[ '100vh', '800px' ],
		[ 'calc(100vh - 80px)', 'calc(800px - 80px)' ],
		[ '50svh', '400px' ],
		[ '50dvh 25lvh', '400px 200px' ],
		[ '-10vh', '-80px' ],
		[ '.5vh', '4px' ],
		[ '12.345vh', '98.76px' ],
		[ '100vb', '800px' ],
		[ '100vmin', '800px' ],
		[ '100vmax', '1300px' ],
		[ '100VH', '800px' ],
	] )( 'pins %s to %s', ( value, pinned ) => {
		expect( pinViewportLengths( value, WIDTH, HEIGHT ) ).toBe( pinned );
	} );

	it.each( [
		[ 'width units', '100vw' ],
		[ 'a custom property name', 'var(--gap-10vh)' ],
		[ 'a longer word', '10vhx' ],
		[ 'other lengths', '2rem 10% 3px' ],
	] )( 'leaves %s alone', ( _label, value ) => {
		expect( pinViewportLengths( value, WIDTH, HEIGHT ) ).toBe( value );
	} );
} );

describe( 'pinViewportUnits', () => {
	beforeEach( () => {
		document.head.innerHTML =
			'<style>.hero{height:50vh}.keep{padding:2rem}.loud{min-height:100vh !important}</style>';
		document.body.innerHTML =
			'<div class="cover" style="min-height:100vh;padding:1rem"></div>';
	} );

	function rule( selector: string ): CSSStyleRule {
		const rules = Array.from(
			( document.styleSheets[ 0 ] as CSSStyleSheet ).cssRules
		) as CSSStyleRule[];

		return rules.find(
			( r ) => r.selectorText === selector
		) as CSSStyleRule;
	}

	it( 'pins inline styles and leaves their other declarations', () => {
		pinViewportUnits( document, WIDTH, HEIGHT );

		const cover = document.querySelector( '.cover' ) as HTMLElement;

		expect( cover.style.minHeight ).toBe( '800px' );
		expect( cover.style.padding ).toBe( '1rem' );
	} );

	it( 'pins stylesheet rules and keeps their priority', () => {
		pinViewportUnits( document, WIDTH, HEIGHT );

		expect( rule( '.hero' ).style.getPropertyValue( 'height' ) ).toBe(
			'400px'
		);
		expect( rule( '.keep' ).style.getPropertyValue( 'padding' ) ).toBe(
			'2rem'
		);
		expect( rule( '.loud' ).style.getPropertyValue( 'min-height' ) ).toBe(
			'800px'
		);
		expect(
			rule( '.loud' ).style.getPropertyPriority( 'min-height' )
		).toBe( 'important' );
	} );
} );
