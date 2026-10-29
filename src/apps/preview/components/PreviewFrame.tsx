import { useEffect, useRef, useState } from 'react';

export const MIN_HEIGHT = 140;
export const MAX_HEIGHT = 400;
const DESKTOP_WIDTH = 1200;
const TRANSPARENT = /^(transparent|rgba\(0, 0, 0, 0\))$/;

interface Props {
	html: string;
	title: string;
	/** Render at desktop width and scale down to fit, for a whole page. */
	desktop: boolean;
}

interface Size {
	height: number;
	scale: number;
	background: string;
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
 * The rendered document, as tall as its content from 140 to 400 pixels.
 * Taller content scrolls inside, and shorter content is centered on the
 * theme's background. The frame runs no scripts and ignores the pointer, so
 * links don't navigate and scrolling reaches the card.
 */
export function PreviewFrame( { html, title, desktop }: Props ) {
	const frameRef = useRef< HTMLIFrameElement >( null );
	const boxRef = useRef< HTMLDivElement >( null );
	const [ size, setSize ] = useState< Size | null >( null );

	useEffect( () => {
		const frame = frameRef.current;
		const box = boxRef.current;

		if ( ! frame || ! box ) {
			return;
		}

		let content: ResizeObserver | null = null;

		const measure = () => {
			const doc = frame.contentDocument;

			if ( ! doc?.body ) {
				return;
			}

			setSize( {
				height: Math.ceil( doc.body.scrollHeight ),
				scale: desktop
					? Math.min( 1, box.clientWidth / DESKTOP_WIDTH )
					: 1,
				background: backgroundOf( doc ),
			} );
		};

		const onLoad = () => {
			content?.disconnect();
			measure();

			if ( frame.contentDocument?.body ) {
				content = new ResizeObserver( measure );
				content.observe( frame.contentDocument.body );
			}
		};

		const outer = new ResizeObserver( measure );
		outer.observe( box );
		frame.addEventListener( 'load', onLoad );

		return () => {
			frame.removeEventListener( 'load', onLoad );
			content?.disconnect();
			outer.disconnect();
		};
	}, [ html, desktop ] );

	const shown = size ? size.height * size.scale : 0;
	const height = size
		? Math.min( Math.max( shown, MIN_HEIGHT ), MAX_HEIGHT )
		: MIN_HEIGHT;

	return (
		<div
			ref={ boxRef }
			className={ `pc-body${ shown > MAX_HEIGHT ? ' scrolls' : '' }` }
			style={ { height, background: size?.background } }
		>
			<div
				className="pc-frame"
				style={ {
					height: shown || 1,
					visibility: size ? 'visible' : 'hidden',
				} }
			>
				<iframe
					ref={ frameRef }
					title={ title }
					srcDoc={ html }
					sandbox="allow-same-origin"
					tabIndex={ -1 }
					style={ {
						width: desktop ? DESKTOP_WIDTH : '100%',
						height: size?.height || 1,
						transform: desktop
							? `scale(${ size?.scale ?? 1 })`
							: undefined,
					} }
				/>
			</div>
		</div>
	);
}
