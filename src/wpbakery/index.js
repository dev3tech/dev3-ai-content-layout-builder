/* global vc */
import { __ } from '@wordpress/i18n';
import { readField, writeField, wandButton, whenReady, SKIP_NAME } from '../bridges/fields';
import styles from './wpbakery.module.css';

const TEXT_TYPES = [ 'textfield', 'textarea', 'textarea_html', 'textarea_raw_html' ];

function paramEl( root, name ) {
	return root.querySelector( `.vc_shortcode-param[data-param_name="${ name }"]` );
}

function fieldFor( param ) {
	const type = param.getAttribute( 'data-param_type' );
	const name = param.getAttribute( 'data-param_name' ) || '';
	if ( type === 'textarea_html' ) {
		return 'wysiwyg';
	}
	if ( type === 'textarea_raw_html' ) {
		return 'html';
	}
	if ( type === 'textarea' ) {
		return 'textarea';
	}
	if ( /title|heading|text/.test( name ) ) {
		return 'heading';
	}
	return 'text';
}

function openForParam( param, fieldOverride ) {
	const label = ( param.querySelector( '.wpb_element_label' ) || {} ).textContent || '';
	const field = fieldOverride || fieldFor( param );
	window.dev3cl.open( {
		context: 'wpbakery',
		builder: 'wpbakery',
		title: label ? `DEV3 AI · ${ label.trim() }` : undefined,
		tabs: [ 'write', 'image', 'history' ],
		text: readField( param ),
		field,
		onInsert: ( value, { mode } ) => {
			writeField( param, mode === 'append' ? readField( param ) + '\n' + value : value );
			window.dev3cl.close();
		},
	} );
}

function decorate( panel ) {
	panel.querySelectorAll( '.vc_shortcode-param' ).forEach( ( param ) => {
		const type = param.getAttribute( 'data-param_type' );
		const name = param.getAttribute( 'data-param_name' ) || '';
		if ( param.dataset.dev3cl || ! TEXT_TYPES.includes( type ) || SKIP_NAME.test( name ) ) {
			return;
		}
		const label = param.querySelector( '.wpb_element_label' );
		if ( ! label ) {
			return;
		}
		param.dataset.dev3cl = '1';
		label.appendChild( wandButton( styles.wand, __( 'Write with DEV3 AI', 'dev3-ai-content-layout-builder' ), () => openForParam( param ) ) );
	} );
	panel.querySelectorAll( '.dev3cl-vc-launch' ).forEach( ( btn ) => btn.classList.add( styles.launch ) );
}

/**
 * Inserts shortcodes into the backend editor the way WPBakery's own template panel does;
 * other modes fall back to the clipboard.
 *
 * @param {string} shortcodes Shortcode markup.
 * @return {boolean} Inserted directly.
 */
function insertShortcodes( shortcodes ) {
	if ( window.vc && vc.storage && vc.storage.parseContent && vc.shortcodes && vc.shortcodes.create && ! vc.frame_window ) {
		const models = vc.storage.parseContent( {}, shortcodes );
		Object.values( models ).forEach( ( model ) => vc.shortcodes.create( model ) );
		return true;
	}
	if ( window.vc && vc.builder && vc.builder.buildFromContent && vc.frame_window ) {
		try {
			vc.builder.buildFromContent( shortcodes );
			return true;
		} catch ( e ) {}
	}
	return false;
}

function openLayout() {
	window.dev3cl.open( {
		context: 'wpbakery',
		builder: 'wpbakery',
		title: __( 'DEV3 AI · Generate section', 'dev3-ai-content-layout-builder' ),
		tab: 'layout',
		tabs: [ 'layout', 'history' ],
		onInsertLayout: ( payload ) => {
			if ( insertShortcodes( payload.wpbakery ) ) {
				window.dev3cl.close();
				return;
			}
			if ( window.navigator.clipboard ) {
				window.navigator.clipboard.writeText( payload.wpbakery );
			}
			// eslint-disable-next-line no-alert
			window.alert( __( 'The shortcode was copied to your clipboard. Switch to the Backend Editor (or Classic Mode) and paste it to add the section.', 'dev3-ai-content-layout-builder' ) );
		},
	} );
}

function addNavbarButton() {
	const nav = document.querySelector( '#vc_navbar .vc_navbar-nav' );
	if ( ! nav || nav.querySelector( '.dev3cl-vc-nav' ) ) {
		return;
	}
	const li = document.createElement( 'li' );
	li.className = 'vc_pull-right dev3cl-vc-nav';
	const a = document.createElement( 'a' );
	a.href = '#';
	a.className = 'vc_icon-btn ' + styles.navbtn;
	a.title = __( 'Generate a section with DEV3 AI', 'dev3-ai-content-layout-builder' );
	a.innerHTML = '<span class="dev3cl-wand-icon" style="width:18px;height:18px" aria-hidden="true"></span>';
	a.addEventListener( 'click', ( e ) => {
		e.preventDefault();
		openLayout();
	} );
	li.appendChild( a );
	nav.appendChild( li );
}

function boot() {
	document.addEventListener( 'click', ( e ) => {
		const btn = e.target.closest && e.target.closest( '.dev3cl-vc-launch' );
		if ( ! btn ) {
			return;
		}
		e.preventDefault();
		const wrap = btn.closest( '.dev3cl-vc-param' );
		const panel = btn.closest( '.vc_ui-panel-window, .vc_edit_form_elements' ) || document;
		const target = paramEl( panel, wrap.getAttribute( 'data-target' ) );
		if ( target ) {
			openForParam( target, wrap.getAttribute( 'data-field' ) );
		}
	} );

	const observer = new window.MutationObserver( () => {
		const panel = document.getElementById( 'vc_ui-panel-edit-element' );
		if ( panel ) {
			decorate( panel );
		}
		addNavbarButton();
	} );
	observer.observe( document.body, { childList: true, subtree: true } );
	addNavbarButton();
}

whenReady( boot );
