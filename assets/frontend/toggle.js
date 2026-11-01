/* DEV3 AI Content & Layout Builder — billing period toggle for generated pricing sections. */
( function () {
	function scopeOf( toggle ) {
		// The toggle controls prices in the same top-level section/row.
		return toggle.closest( '.e-con.e-parent, .elementor-top-section, .vc_row, .wpb_row, section' ) || document;
	}
	function apply( toggle, index ) {
		toggle.querySelectorAll( '[data-dev3cl-set]' ).forEach( function ( btn ) {
			var on = btn.getAttribute( 'data-dev3cl-set' ) === String( index );
			btn.classList.toggle( 'is-active', on );
			btn.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
		} );
		var scope = scopeOf( toggle );
		// Pricing columns may sit in the next section; include following siblings until another toggle.
		var scopes = [ scope ];
		var next = scope.nextElementSibling;
		while ( next && ! next.querySelector( '[data-dev3cl-toggle]' ) && next.querySelector( '[data-dev3cl-period]' ) ) {
			scopes.push( next );
			next = next.nextElementSibling;
		}
		scopes.forEach( function ( el ) {
			el.querySelectorAll( '[data-dev3cl-period]' ).forEach( function ( span ) {
				span.style.display = span.getAttribute( 'data-dev3cl-period' ) === String( index ) ? '' : 'none';
			} );
		} );
	}
	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest ? e.target.closest( '[data-dev3cl-set]' ) : null;
		if ( ! btn ) {
			return;
		}
		var toggle = btn.closest( '[data-dev3cl-toggle]' );
		if ( toggle ) {
			e.preventDefault();
			apply( toggle, btn.getAttribute( 'data-dev3cl-set' ) );
		}
	} );
} )();
