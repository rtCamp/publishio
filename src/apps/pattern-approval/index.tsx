import { createRoot } from 'react-dom/client';
import { App } from './App';

import './styles.scss';

const rootEl = document.getElementById( 'root' );
if ( ! rootEl ) {
	throw new Error( 'Pattern Approval: mount point #root not found in DOM.' );
}
createRoot( rootEl ).render( <App /> );
