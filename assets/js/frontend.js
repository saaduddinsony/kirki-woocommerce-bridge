/**
 * Kirki WooCommerce Bridge – frontend behaviour.
 *
 * Mini cart drawer, auto-submitting price filters. No dependencies; jQuery
 * events from WooCommerce are used when jQuery is present.
 *
 * @package KirkiWoo
 */
( function () {
	'use strict';

	var settings = window.kirkiWoo || {};
	var lastToggle = null;

	function drawers() {
		return document.querySelectorAll( '.kwi-mini-cart[data-behavior="drawer"]' );
	}

	function open( root ) {
		var drawer = root.querySelector( '.kwi-mini-cart__drawer' );
		var toggle = root.querySelector( '.kwi-mini-cart__toggle' );
		if ( ! drawer ) {
			return;
		}
		drawer.hidden = false;
		root.classList.add( 'is-open' );
		document.documentElement.classList.add( 'kwi-drawer-open' );
		if ( toggle ) {
			toggle.setAttribute( 'aria-expanded', 'true' );
		}
		var close = drawer.querySelector( '.kwi-mini-cart__close' );
		if ( close ) {
			close.focus();
		}
	}

	function closeAll() {
		drawers().forEach( function ( root ) {
			var drawer = root.querySelector( '.kwi-mini-cart__drawer' );
			var toggle = root.querySelector( '.kwi-mini-cart__toggle' );
			if ( drawer && ! drawer.hidden ) {
				drawer.hidden = true;
				root.classList.remove( 'is-open' );
				if ( toggle ) {
					toggle.setAttribute( 'aria-expanded', 'false' );
				}
			}
		} );
		document.documentElement.classList.remove( 'kwi-drawer-open' );
		if ( lastToggle ) {
			lastToggle.focus();
			lastToggle = null;
		}
	}

	document.addEventListener( 'click', function ( e ) {
		var toggle = e.target.closest && e.target.closest( '.kwi-mini-cart[data-behavior="drawer"] .kwi-mini-cart__toggle' );
		if ( toggle ) {
			e.preventDefault();
			lastToggle = toggle;
			open( toggle.closest( '.kwi-mini-cart' ) );
			return;
		}
		if ( e.target.closest && ( e.target.closest( '.kwi-mini-cart__close' ) || e.target.closest( '[data-kwi-close]' ) ) ) {
			e.preventDefault();
			closeAll();
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( 'Escape' === e.key ) {
			closeAll();
		}
	} );

	// Open the first drawer after an AJAX add to cart.
	if ( window.jQuery ) {
		window.jQuery( document.body ).on( 'added_to_cart', function () {
			if ( ! settings.miniCartAutoOpen ) {
				return;
			}
			var first = drawers()[ 0 ];
			if ( first ) {
				open( first );
			}
		} );
	}

	// Price filters with auto submit.
	document.addEventListener( 'change', function ( e ) {
		var form = e.target.closest && e.target.closest( 'form.kwi-auto-submit' );
		if ( form ) {
			if ( typeof form.requestSubmit === 'function' ) {
				form.requestSubmit();
			} else {
				form.submit();
			}
		}
	} );
} )();
