import { useLayoutEffect, useRef } from 'react';

const MIN_HEIGHT = 140;
const LOADING_HEIGHT = 220;

// The page renders in a desktop window, so breakpoints and viewport units apply as on a desktop screen, then scales down to fit the card.
const DESKTOP_WIDTH = 1300;
const DESKTOP_HEIGHT = 800;

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

/**
 * The rendered document in a desktop-sized window, scaled to the card. The box is as tall as the content, from 140 pixels up to one screen; a longer page scrolls inside the frame, like a browser window. Shorter content is centered on the theme's background.
 *
 * The frame's size never changes, so viewport units resolve as on a desktop and measuring can't feed back into the layout. Sizes are rounded to whole pixels, so the app's height has no fraction left over.
 */
export function PreviewFrame( { html, title, hidden }: Props ) {
	const boxRef = useRef< HTMLDivElement >( null );
	const innerRef = useRef< HTMLDivElement >( null );
	const frameRef = useRef< HTMLIFrameElement >( null );

	// A layout effect runs in the same task as the commit that sets srcDoc, so the frame can't finish loading before the listener is attached.
	useLayoutEffect( () => {
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
			const width = box.offsetWidth;

			if ( ! doc?.body || 0 === width ) {
				return;
			}

			const scale = Math.min( 1, width / DESKTOP_WIDTH );
			const height = Math.min(
				Math.ceil( doc.body.scrollHeight ),
				DESKTOP_HEIGHT
			);
			const shown = Math.ceil( height * scale );

			box.style.height = `${ Math.max( shown, MIN_HEIGHT ) }px`;
			box.style.background = backgroundOf( doc );

			inner.style.height = `${ shown }px`;
			inner.style.visibility = 'visible';

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
					sandbox="allow-scripts allow-same-origin"
					tabIndex={ -1 }
					style={ { width: DESKTOP_WIDTH, height: DESKTOP_HEIGHT } }
				/>
			</div>
		</div>
	);
}
