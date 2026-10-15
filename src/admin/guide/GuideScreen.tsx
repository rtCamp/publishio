/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';

/**
 * External dependencies
 */
import { flushSync } from 'react-dom';

/**
 * Internal dependencies
 */
import { useSiteCheck } from '../shared/site-check/useSiteCheck';
import type { Guide } from './types';
import { GuideList } from './GuideList';
import { GuideDetail } from './GuideDetail';

export function GuideScreen() {
	const [ activeGuide, setActiveGuide ] = useState< Guide | null >( null );

	const siteCheck = useSiteCheck( {
		autoRun: window.publishioAdmin?.autoRunChecks ?? true,
		reuseSaved: true,
	} );

	function navigate( guide: Guide | null ) {
		document.documentElement.dataset[ 'guideTransition' ] = guide
			? 'open'
			: 'close';

		if ( document.startViewTransition ) {
			document.startViewTransition( () => {
				flushSync( () => setActiveGuide( guide ) );
			} );
		} else {
			setActiveGuide( guide );
		}
	}

	return activeGuide ? (
		<GuideDetail guide={ activeGuide } onBack={ () => navigate( null ) } />
	) : (
		<GuideList onOpen={ navigate } siteCheck={ siteCheck } />
	);
}
