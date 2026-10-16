/**
 * Settings screen entry point.
 */
import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

import App from './App';
import './style.scss';

domReady( () => {
	const container = document.getElementById( 'wpmi-settings-root' );

	if ( ! container ) {
		return;
	}

	container.textContent = '';
	createRoot( container ).render( <App /> );
} );
