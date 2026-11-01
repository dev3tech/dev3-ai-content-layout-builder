/**
 * DOM-level read/write for page-builder controls. Writing through the control's own input events
 * means the builder runs its normal change pipeline (model update, undo history, live preview).
 */

const fire = ( el, types ) => types.forEach( ( type ) => el.dispatchEvent( new window.Event( type, { bubbles: true } ) ) );

function tinyFor( root ) {
	const area = root.querySelector( 'textarea.wp-editor-area, textarea[id]' );
	if ( ! area || ! window.tinymce ) {
		return { area, editor: null };
	}
	return { area, editor: window.tinymce.get( area.id ) };
}

function aceFor( root ) {
	const el = root.querySelector( '.ace_editor, .elementor-code-editor' );
	if ( ! el || ! window.ace ) {
		return null;
	}
	try {
		return window.ace.edit( el );
	} catch ( e ) {
		return null;
	}
}

/**
 * Reads a control's current value.
 *
 * @param {Element} root Control wrapper element.
 * @return {string} Value.
 */
export function readField( root ) {
	const ace = aceFor( root );
	if ( ace ) {
		return ace.getValue();
	}
	const { area, editor } = tinyFor( root );
	if ( editor && ! editor.isHidden() ) {
		return editor.getContent();
	}
	if ( area ) {
		return area.value;
	}
	const input = root.querySelector( 'input[type="text"], input:not([type]), textarea, [contenteditable="true"]' );
	if ( ! input ) {
		return '';
	}
	return input.isContentEditable ? input.innerHTML : input.value;
}

/**
 * Writes a control's value and notifies the builder.
 *
 * @param {Element} root  Control wrapper element.
 * @param {string}  value Value.
 * @return {boolean} Whether a writable input was found.
 */
export function writeField( root, value ) {
	const ace = aceFor( root );
	if ( ace ) {
		ace.setValue( value, -1 );
		return true;
	}
	const { area, editor } = tinyFor( root );
	if ( editor ) {
		editor.setContent( value );
		editor.save();
		editor.fire( 'change' );
		editor.fire( 'keyup' );
		if ( area ) {
			area.value = editor.getContent();
			fire( area, [ 'input', 'change', 'keyup' ] );
		}
		return true;
	}
	if ( area ) {
		area.value = value;
		fire( area, [ 'input', 'change', 'keyup' ] );
		return true;
	}
	const input = root.querySelector( 'input[type="text"], input:not([type]), textarea, [contenteditable="true"]' );
	if ( ! input ) {
		return false;
	}
	if ( input.isContentEditable ) {
		input.innerHTML = value;
		fire( input, [ 'input', 'change' ] );
		return true;
	}
	// React/Backbone-safe setter.
	const proto = input.tagName === 'TEXTAREA' ? window.HTMLTextAreaElement.prototype : window.HTMLInputElement.prototype;
	const setter = Object.getOwnPropertyDescriptor( proto, 'value' );
	if ( setter && setter.set ) {
		setter.set.call( input, value );
	} else {
		input.value = value;
	}
	fire( input, [ 'input', 'change', 'keyup' ] );
	if ( window.jQuery ) {
		window.jQuery( input ).trigger( 'change' );
	}
	return true;
}

/**
 * Builds a wand button.
 *
 * @param {string}   className Extra class.
 * @param {string}   label     Accessible label.
 * @param {Function} onClick   Handler.
 * @return {HTMLButtonElement} Button.
 */
export function wandButton( className, label, onClick ) {
	const btn = document.createElement( 'button' );
	btn.type = 'button';
	btn.className = className;
	btn.title = label;
	btn.setAttribute( 'aria-label', label );
	btn.innerHTML = '<span class="dev3cl-wand-icon" aria-hidden="true"></span>';
	btn.addEventListener( 'click', ( e ) => {
		e.preventDefault();
		e.stopPropagation();
		onClick( e );
	} );
	return btn;
}

/**
 * Waits for window.dev3cl (the shared modal bundle).
 *
 * @param {Function} cb Callback.
 */
export function whenReady( cb ) {
	if ( window.dev3cl && window.dev3cl.open ) {
		cb();
		return;
	}
	document.addEventListener( 'dev3cl:ready', cb, { once: true } );
}

/** Text-ish control names that are never content (ids, classes, links, CSS). */
export const SKIP_NAME = /(^_|css|class|(^|_)id$|url|link|anchor|attribute|selector|custom_?attributes|html_tag|tag$|key$|token)/i;
