/**
 * WordPress dependencies
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { copyToClipboard } from './utils';

/**
 * Copy text, and report it as copied for two seconds.
 */
export function useCopied() {
	const [ copied, setCopied ] = useState( false );
	const timer = useRef< ReturnType< typeof setTimeout > >();

	useEffect( () => () => clearTimeout( timer.current ), [] );

	const copyText = useCallback( async ( text: string ) => {
		await copyToClipboard( text );
		setCopied( true );
		clearTimeout( timer.current );
		timer.current = setTimeout( () => setCopied( false ), 2000 );
	}, [] );

	return { copied, copyText };
}
