/* global elementor, elementorCommon, $e */
import { __ } from '@wordpress/i18n';
import { readField, writeField, wandButton, whenReady, SKIP_NAME } from '../bridges/fields';
import styles from './elementor.module.css';

const CONTROL_SELECTOR = '.elementor-control-type-text, .elementor-control-type-textarea, .elementor-control-type-wysiwyg, .elementor-control-type-code';

const fieldHint = ( controlEl, widgetType ) => {
	if ( controlEl.classList.contains( 'elementor-control-type-wysiwyg' ) ) {
		return 'wysiwyg';
	}
	if ( controlEl.classList.contains( 'elementor-control-type-code' ) ) {
		return 'html';
	}
	const name = controlName( controlEl );
	if ( widgetType === 'heading' || /title|heading/.test( name ) ) {
		return 'heading';
	}
	if ( widgetType === 'button' || /button|btn|^text$/.test( name ) ) {
		return widgetType === 'button' ? 'button' : 'text';
	}
	return controlEl.classList.contains( 'elementor-control-type-textarea' ) ? 'textarea' : 'text';
};

function controlName( el ) {
	const cls = Array.from( el.classList ).find( ( c ) => c.startsWith( 'elementor-control-' ) && ! c.startsWith( 'elementor-control-type-' ) && ! /^elementor-control-(separator|responsive|dynamic|label|field|show|hidden)/.test( c ) );
	return cls ? cls.replace( 'elementor-control-', '' ) : '';
}

function editedContainer() {
	try {
		const page = elementor.getPanelView().getCurrentPageView();
		const view = page.getOption( 'editedElementView' );
		if ( view && view.getContainer ) {
			return view.getContainer();
		}
		if ( page.container ) {
			return page.container;
		}
	} catch ( e ) {}
	return null;
}

function elementorMode() {
	try {
		return elementorCommon.config.experimentalFeatures.container ? 'container' : 'section';
	} catch ( e ) {
		return '';
	}
}

function openForControl( controlEl ) {
	const container = editedContainer();
	const widgetType = container && container.model ? container.model.get( 'widgetType' ) : '';
	const field = fieldHint( controlEl, widgetType );
	const label = ( controlEl.querySelector( '.elementor-control-title' ) || {} ).textContent || '';
	window.dev3cl.open( {
		context: 'elementor',
		builder: 'elementor',
		elementorMode: elementorMode(),
		title: label ? `DEV3 AI · ${ label.trim() }` : undefined,
		tabs: [ 'write', 'image', 'history' ],
		text: readField( controlEl ),
		field,
		onInsert: ( value, { mode } ) => {
			const next = mode === 'append' ? readField( controlEl ) + '\n' + value : value;
			if ( ! writeField( controlEl, next ) && container ) {
				$e.run( 'document/elements/settings', { container, settings: { [ controlName( controlEl ) ]: next }, options: { external: true } } );
			}
			window.dev3cl.close();
		},
	} );
}

function isDefaultItem( item ) {
	return /#\s?\d+$/.test( item.tab_title || '' );
}

function openForRepeater( container, name ) {
	const current = container.settings.get( name );
	const items = current && current.toJSON ? current.toJSON() : [];
	window.dev3cl.open( {
		context: 'elementor',
		builder: 'elementor',
		title: __( 'DEV3 AI · Generate items', 'dev3-ai-content-layout-builder' ),
		tabs: [ 'write', 'history' ],
		field: 'repeater',
		task: 'items',
		text: items.filter( ( i ) => ! isDefaultItem( i ) ).map( ( i ) => `${ i.tab_title }: ${ i.tab_content }` ).join( '\n' ),
		onInsertItems: ( list ) => {
			// Replace Elementor's placeholder items ("Accordion #1"), keep real ones.
			if ( items.length && items.every( isDefaultItem ) ) {
				for ( let i = items.length - 1; i >= 0; i-- ) {
					$e.run( 'document/repeater/remove', { container, name, index: i } );
				}
			}
			list.forEach( ( item ) => {
				$e.run( 'document/repeater/insert', { container, name, model: { tab_title: item.title, tab_content: item.text } } );
			} );
			window.dev3cl.close();
		},
	} );
}

function openLayout( at ) {
	window.dev3cl.open( {
		context: 'elementor',
		builder: 'elementor',
		elementorMode: elementorMode(),
		title: __( 'DEV3 AI · Generate section', 'dev3-ai-content-layout-builder' ),
		tab: 'layout',
		tabs: [ 'layout', 'history' ],
		onInsertLayout: ( payload ) => {
			const target = elementor.getPreviewContainer();
			let index = typeof at === 'number' ? at : undefined;
			payload.elementor.forEach( ( model ) => {
				$e.run( 'document/elements/create', { container: target, model, options: index === undefined ? { edit: false } : { at: index++, edit: false } } );
			} );
			window.dev3cl.close();
		},
	} );
}

function decorate( root ) {
	root.querySelectorAll( CONTROL_SELECTOR ).forEach( ( controlEl ) => {
		if ( controlEl.dataset.dev3cl || SKIP_NAME.test( controlName( controlEl ) ) ) {
			return;
		}
		controlEl.dataset.dev3cl = '1';
		const button = wandButton( styles.wand, __( 'Write with DEV3 AI', 'dev3-ai-content-layout-builder' ), () => openForControl( controlEl ) );
		// Inline after the label keeps clear of Elementor's own header actions (dynamic tags, Elementor AI).
		const label = controlEl.querySelector( '.elementor-control-title' );
		if ( label ) {
			label.after( button );
		} else {
			const host = controlEl.querySelector( '.elementor-control-content' ) || controlEl;
			host.classList.add( styles.host );
			button.classList.add( styles.floating );
			host.appendChild( button );
		}
	} );
}

function registerControlView() {
	// UI-only control: extend the value-less base view.
	const Base = elementor.modules.controls.Base || elementor.modules.controls.BaseData;
	const inherited = ( view, key ) => {
		const value = Base.prototype[ key ];
		return ( typeof value === 'function' ? value.call( view ) : value ) || {};
	};
	const View = Base.extend( {
		ui() {
			return { ...inherited( this, 'ui' ), launch: '.dev3cl-el-launch' };
		},
		events() {
			return { ...inherited( this, 'events' ), 'click @ui.launch': 'onLaunch' };
		},
		onRender() {
			Base.prototype.onRender && Base.prototype.onRender.apply( this, arguments );
			this.el.querySelector( '.dev3cl-el-launch' )?.classList.add( styles.launch );
		},
		onLaunch( event ) {
			event.preventDefault();
			const target = this.model.get( 'target' );
			const container = this.container || this.options.container || editedContainer();
			if ( this.model.get( 'field' ) === 'repeater' ) {
				openForRepeater( container, target );
				return;
			}
			const panel = this.el.closest( '.elementor-controls-stack' ) || document;
			const controlEl = panel.querySelector( `.elementor-control-${ target }` );
			if ( controlEl ) {
				openForControl( controlEl );
			}
		},
	} );
	elementor.addControlView( 'gemini_ai', View );
}

function injectAddSectionButton() {
	const tpl = document.getElementById( 'tmpl-elementor-add-section' );
	if ( ! tpl || tpl.textContent.includes( 'dev3cl-add-section' ) ) {
		return;
	}
	const button =
		'<div class="elementor-add-section-area-button dev3cl-add-section" title="' +
		__( 'Generate a section with DEV3 AI', 'dev3-ai-content-layout-builder' ) +
		'" style="background:linear-gradient(135deg,#4f46e5,#7c3aed);color:#fff;margin-inline-start:8px">' +
		'<svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path fill="currentColor" d="M12 2l1.9 5.1L19 9l-5.1 1.9L12 16l-1.9-5.1L5 9l5.1-1.9L12 2zm7 11l.95 2.05L22 16l-2.05.95L19 19l-.95-2.05L16 16l2.05-.95L19 13zM6 15l.7 1.3L8 17l-1.3.7L6 19l-.7-1.3L4 17l1.3-.7L6 15z"/></svg></div>';
	tpl.textContent = tpl.textContent.replace( /(<div class="elementor-add-section-drag-title)/, button + '$1' );
}

function topLevelIndex( addSectionEl ) {
	const inline = addSectionEl.closest( '.elementor-add-section-inline' );
	if ( ! inline ) {
		return undefined;
	}
	let index = 0;
	let node = inline.previousElementSibling;
	while ( node ) {
		if ( node.classList.contains( 'elementor-element' ) ) {
			index++;
		}
		node = node.previousElementSibling;
	}
	return index;
}

function boot() {
	registerControlView();
	injectAddSectionButton();

	const observer = new window.MutationObserver( () => {
		const panel = document.getElementById( 'elementor-panel-page-editor' ) || document.getElementById( 'elementor-panel' );
		if ( panel ) {
			decorate( panel );
		}
	} );
	const start = () => {
		const panel = document.getElementById( 'elementor-panel' );
		if ( panel ) {
			observer.observe( panel, { childList: true, subtree: true } );
			decorate( panel );
		}
	};
	elementor.on( 'panel:init', start );
	start();

	elementor.on( 'preview:loaded', () => {
		elementor.$previewContents.on( 'click', '.dev3cl-add-section', ( event ) => {
			event.preventDefault();
			event.stopPropagation();
			openLayout( topLevelIndex( event.currentTarget ) );
		} );
	} );

	document.addEventListener( 'keydown', ( e ) => {
		if ( ( e.metaKey || e.ctrlKey ) && e.shiftKey && e.key.toLowerCase() === 'g' && ! window.dev3cl.isOpen() ) {
			e.preventDefault();
			openLayout();
		}
	} );
}

// The control view must be registered before panels render. Elementor triggers elementor/init
// as a jQuery event on window, so a native listener would never fire.
let booted = false;
const start = () => {
	if ( ! booted ) {
		booted = true;
		whenReady( boot );
	}
};
if ( window.elementor && window.elementor.config && window.elementor.modules && window.elementor.modules.controls ) {
	start();
} else if ( window.jQuery ) {
	window.jQuery( window ).on( 'elementor/init', start );
}
