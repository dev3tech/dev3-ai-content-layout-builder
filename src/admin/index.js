import { createElement, render } from '@wordpress/element';
import * as element from '@wordpress/element';
import App from './App';
import './admin.css';

const node = document.getElementById( 'dev3cl-admin-root' );
if ( node ) {
	if ( element.createRoot ) {
		element.createRoot( node ).render( createElement( App ) );
	} else {
		render( createElement( App ), node );
	}
}
