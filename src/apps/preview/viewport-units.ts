/**
 * A preview frame sized to its content has no real window height: `100vh` resolves against the whole page, so a full-height cover grows the frame, which grows the cover, without end. Pinning viewport-height lengths to a desktop window renders the page as a visitor sees it.
 */

// The window height a preview pretends to have, so viewport-height lengths render as on a desktop screen.
export const PREVIEW_VIEWPORT_HEIGHT = 800;

// A length in a viewport unit that depends on the window's height, including the small/large/dynamic variants. Number boundaries keep custom property names such as `--gap-10vh` intact.
const UNIT =
	/(?<![\w.-])(-?(?:\d+\.?\d*|\.\d+))([sld]?(?:vh|vb|vmin|vmax))(?![\w-])/gi;
const HAS_UNIT = new RegExp( UNIT.source, 'i' );

/**
 * A CSS value with its viewport-height lengths as pixels, for a window of the given size.
 *
 * @param value  CSS value.
 * @param width  Window width in px.
 * @param height Window height in px.
 */
export function pinViewportLengths(
	value: string,
	width: number,
	height: number
): string {
	const sizes: Record< string, number > = {
		vh: height,
		vb: height,
		vmin: Math.min( width, height ),
		vmax: Math.max( width, height ),
	};

	return value.replace( UNIT, ( _match, amount: string, unit: string ) => {
		const size =
			sizes[ unit.toLowerCase().replace( /^[sld]/, '' ) ] ?? height;
		const px =
			Math.round( ( parseFloat( amount ) * size * 1000 ) / 100 ) / 1000;

		return `${ px }px`;
	} );
}

function pinDeclarations(
	style: CSSStyleDeclaration,
	width: number,
	height: number
): void {
	if ( ! HAS_UNIT.test( style.cssText ) ) {
		return;
	}

	for ( let index = 0; index < style.length; index++ ) {
		const property = style[ index ] ?? '';
		const value = style.getPropertyValue( property );
		const pinned = pinViewportLengths( value, width, height );

		if ( pinned !== value ) {
			style.setProperty(
				property,
				pinned,
				style.getPropertyPriority( property )
			);
		}
	}
}

function pinRules( rules: CSSRuleList, width: number, height: number ): void {
	Array.from( rules ).forEach( ( rule ) => {
		// Duck-typed: rules from the frame's document fail `instanceof` against this window's CSSOM classes.
		if ( 'style' in rule ) {
			pinDeclarations( ( rule as CSSStyleRule ).style, width, height );
		}

		if ( 'cssRules' in rule ) {
			pinRules( ( rule as CSSGroupingRule ).cssRules, width, height );
		}
	} );
}

/**
 * Pin every viewport-height length in a preview document, in its readable stylesheets and inline styles, to a window of the given size.
 *
 * @param doc    Preview document.
 * @param width  Window width in px.
 * @param height Window height in px.
 */
export function pinViewportUnits(
	doc: Document,
	width: number,
	height: number = PREVIEW_VIEWPORT_HEIGHT
): void {
	Array.from( doc.styleSheets ).forEach( ( sheet ) => {
		let rules: CSSRuleList;

		// A cross-origin stylesheet can't be read; previews inline their own.
		try {
			rules = sheet.cssRules;
		} catch {
			return;
		}

		pinRules( rules, width, height );
	} );

	doc.querySelectorAll< HTMLElement | SVGElement >( '[style]' ).forEach(
		( element ) => pinDeclarations( element.style, width, height )
	);
}
