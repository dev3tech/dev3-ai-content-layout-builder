/**
 * Shared AI modal. Bridges call window.dev3cl.open( options ).
 *
 * Options: {
 *   context, title, tab, tabs[], task, text, field, format, builder, elementorMode,
 *   attachmentId, imageUrl,
 *   onInsert( value, { mode, result } ), onInsertItems( items ), onInsertLayout( payload ), onApplyMedia( data )
 * }
 */
import { createElement, render } from '@wordpress/element';
import * as element from '@wordpress/element';
import AIModal from './AIModal';
import * as api from '../shared/api';
import config from '../shared/config';
import './modal.css';

let root = null;
let container = null;
let current = null;

function mount( node ) {
	if ( ! container ) {
		container = document.createElement( 'div' );
		container.id = 'dev3cl-modal-host';
		document.body.appendChild( container );
	}
	// WP 6.2+ ships React 18 (createRoot); WP 6.0/6.1 still use legacy render.
	if ( element.createRoot ) {
		if ( ! root ) {
			root = element.createRoot( container );
		}
		root.render( node );
	} else {
		render( node, container );
	}
}

function close() {
	api.cancel();
	current = null;
	mount( null );
}

function open( options = {} ) {
	current = { ...options, key: Date.now() };
	mount( createElement( AIModal, { ...current, onClose: close } ) );
}

window.dev3cl = Object.assign( window.dev3cl || {}, {
	open,
	close,
	api,
	config,
	isOpen: () => !! current,
} );

document.dispatchEvent( new CustomEvent( 'dev3cl:ready' ) );
