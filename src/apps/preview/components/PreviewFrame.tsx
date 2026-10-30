import { useEffect, useRef } from 'react';

import { pinViewportUnits } from '../viewport-units';

const MIN_HEIGHT = 140;
const MAX_HEIGHT = 400;
const LOADING_HEIGHT = 220;

// Render at desktop width so desktop breakpoints apply, then scale down to fit, as the user sees the page on a desktop screen.
const DESKTOP_WIDTH = 1300;

const TRANSPARENT = /^(transparent|rgba\(0, 0, 0, 0\))$/;

interface Props {
	html: string;
	title: string;
	hidden: boolean;
}

function backgroundOf( doc: Document ): string {
	for ( const element of [ doc.body, doc.documentElement ] ) {
		const color = element
			? doc.defaultView?.getComputedStyle( element ).backgroundColor
			: '';

		if ( color && ! TRANSPARENT.test( color ) ) {
			return color;
		}
	}

	return '#fff';
}

/** The width a classic scrollbar takes from the box; zero with overlay scrollbars. */
function scrollbarWidth( box: HTMLElement ): number {
	const overflow = box.style.overflowY;

	box.style.overflowY = 'scroll';
	const width = box.offsetWidth - box.clientWidth;
	box.style.overflowY = overflow;

	return width;
}

/**
 * The rendered document at desktop width, scaled to the card, as tall as its content from 140 to 400 pixels. Taller content scrolls inside the box, and shorter content is centered on the theme's background.
 *
 * Sizes are set on the elements directly and rounded to whole pixels, so measuring never re-renders and the app's height has no fraction left over. The scrollbar is decided from the width without it, so showing it can't change the scale and hide it again.
 */
export function PreviewFrame( { html, title, hidden }: Props ) {
	const boxRef = useRef< HTMLDivElement >( null );
	const innerRef = useRef< HTMLDivElement >( null );
	const frameRef = useRef< HTMLIFrameElement >( null );

	useEffect( () => {
		const box = boxRef.current;
		const inner = innerRef.current;
		const frame = frameRef.current;

		if ( ! box || ! inner || ! frame ) {
			return;
		}

		let frameId = 0;
		let content: ResizeObserver | null = null;

		const measure = () => {
			const doc = frame.contentDocument;
			const full = box.offsetWidth;

			if ( ! doc?.body || 0 === full ) {
				return;
			}

			// The frame is as tall as its content, so it never scrolls; a scrollbar inside it would narrow the layout below desktop width.
			doc.documentElement.style.overflow = 'hidden';

			const height = Math.ceil( doc.body.scrollHeight );
			const narrow = Math.min(
				1,
				( full - scrollbarWidth( box ) ) / DESKTOP_WIDTH
			);
			const scrolls = Math.ceil( height * narrow ) > MAX_HEIGHT;
			const scale = scrolls
				? narrow
				: Math.min( 1, full / DESKTOP_WIDTH );
			const shown = Math.ceil( height * scale );

			box.style.overflowY = scrolls ? 'scroll' : 'hidden';
			box.style.height = `${
				scrolls ? MAX_HEIGHT : Math.max( shown, MIN_HEIGHT )
			}px`;
			box.style.background = backgroundOf( doc );

			inner.style.height = `${ shown }px`;
			inner.style.visibility = 'visible';

			frame.style.width = `${ DESKTOP_WIDTH }px`;
			frame.style.height = `${ height }px`;
			frame.style.transform = `scale(${ scale })`;
		};

		const schedule = () => {
			if ( ! frameId ) {
				frameId = requestAnimationFrame( () => {
					frameId = 0;
					measure();
				} );
			}
		};

		const onLoad = () => {
			const doc = frame.contentDocument;

			if ( ! doc?.body ) {
				return;
			}

			// The frame takes its content's height, so a 100vh section would grow with it.
			pinViewportUnits( doc, DESKTOP_WIDTH );

			content?.disconnect();
			content = new ResizeObserver( schedule );
			content.observe( doc.body );
			schedule();
		};

		const outer = new ResizeObserver( schedule );
		outer.observe( box );
		frame.addEventListener( 'load', onLoad );

		return () => {
			frame.removeEventListener( 'load', onLoad );
			content?.disconnect();
			outer.disconnect();
			cancelAnimationFrame( frameId );
		};
	}, [ html ] );

	return (
		<div
			ref={ boxRef }
			className="pc-body frame"
			hidden={ hidden }
			style={ { height: LOADING_HEIGHT } }
		>
			<div
				ref={ innerRef }
				className="pc-frame"
				style={ { visibility: 'hidden' } }
			>
				<iframe
					ref={ frameRef }
					title={ title }
					srcDoc={ html }
					sandbox="allow-same-origin"
					scrolling="no"
					tabIndex={ -1 }
					style={ { width: DESKTOP_WIDTH } }
				/>
			</div>
		</div>
	);
}
