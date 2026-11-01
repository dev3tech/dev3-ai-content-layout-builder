import { __ } from '@wordpress/i18n';
import { whenReady } from '../bridges/fields';

const fire = ( el ) => {
	el.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
	el.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );
};

/**
 * Mirrors saved values into whichever attachment form is visible (media modal or Edit Media screen),
 * so the user sees them without a reload and a later "Update" does not overwrite them.
 *
 * @param {Element} scope Container of the clicked button.
 * @param {Object}  data  { alt, caption, title, description }.
 */
function reflect( scope, data ) {
	const map = {
		alt: [ '[data-setting="alt"] textarea', '[data-setting="alt"] input', '#attachment_alt' ],
		caption: [ '[data-setting="caption"] textarea', '#attachment_caption' ],
		title: [ '[data-setting="title"] input', '#title' ],
		description: [ '[data-setting="description"] textarea', '#attachment_content' ],
	};
	Object.keys( map ).forEach( ( key ) => {
		if ( data[ key ] === undefined ) {
			return;
		}
		map[ key ].some( ( selector ) => {
			const el = scope.querySelector( selector ) || document.querySelector( selector );
			if ( el ) {
				el.value = data[ key ];
				fire( el );
				return true;
			}
			return false;
		} );
	} );
}

function previewUrl( id ) {
	try {
		const att = window.wp.media.attachment( id );
		const sizes = att.get( 'sizes' );
		return ( sizes && sizes.medium && sizes.medium.url ) || att.get( 'url' ) || '';
	} catch ( e ) {
		return '';
	}
}

whenReady( () => {
	document.addEventListener( 'click', ( e ) => {
		const btn = e.target.closest && e.target.closest( '.dev3cl-media-launch' );
		if ( ! btn ) {
			return;
		}
		e.preventDefault();
		const id = parseInt( btn.getAttribute( 'data-id' ), 10 ) || 0;
		const scope = btn.closest( '.attachment-details, .media-sidebar, .compat-item, #post-body' ) || document;
		window.dev3cl.open( {
			context: 'media',
			title: __( 'DEV3 AI · Describe image', 'dev3-ai-content-layout-builder' ),
			tab: 'image',
			tabs: [ 'image', 'history' ],
			attachmentId: id,
			imageUrl: previewUrl( id ),
			visionFields: [ 'alt', 'caption', 'title' ],
			onApplyMedia: ( data ) => reflect( scope, data ),
		} );
	} );
} );
