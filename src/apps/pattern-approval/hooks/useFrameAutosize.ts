import { useRef, useEffect } from 'react';

export function useFrameAutosize() {
	const ref = useRef< HTMLIFrameElement >( null );

	useEffect( () => {
		const el = ref.current;
		if ( ! el ) {
			return;
		}

		let ro: ResizeObserver | null = null;

		const onLoad = () => {
			ro?.disconnect();
			const body = el.contentDocument?.body;
			if ( ! body ) {
				return;
			}
			ro = new ResizeObserver( () => {
				requestAnimationFrame( () => {
					el.style.height =
						Math.min( body.scrollHeight, 1200 ) + 'px';
				} );
			} );
			ro.observe( body );
		};

		el.addEventListener( 'load', onLoad );
		return () => {
			el.removeEventListener( 'load', onLoad );
			ro?.disconnect();
		};
	}, [] );

	return ref;
}
