/**
 * Kirki WooCommerce Bridge – editor bridge.
 *
 * Registers every WooCommerce element with the Kirki editor. Previews are
 * rendered by the server (POST kirki-woo/v1/render) so the canvas shows the
 * exact markup the frontend produces.
 *
 * The Kirki editor does not expose React, so elements are created as plain
 * React element objects and hooks come from window.kirki.hooks.
 *
 * @package KirkiWoo
 */
( function () {
	'use strict';

	var REACT_ELEMENT = Symbol.for( 'react.element' );
	var FORWARD_REF = Symbol.for( 'react.forward_ref' );
	var config = window.kirkiWooEditor || null;
	var cache = {};
	var pending = {};

	function h( type, props ) {
		props = props || {};
		var children = Array.prototype.slice.call( arguments, 2 );
		var key = null;
		var ref = null;
		var clean = {};
		Object.keys( props ).forEach( function ( k ) {
			if ( 'key' === k ) {
				key = props.key;
			} else if ( 'ref' === k ) {
				ref = props.ref;
			} else {
				clean[ k ] = props[ k ];
			}
		} );
		if ( children.length === 1 ) {
			clean.children = children[ 0 ];
		} else if ( children.length > 1 ) {
			clean.children = children;
		}
		return { $$typeof: REACT_ELEMENT, type: type, key: key, ref: ref, props: clean, _owner: null, _store: {} };
	}

	function forwardRef( render ) {
		return { $$typeof: FORWARD_REF, render: render };
	}

	function render( name, settings ) {
		var body = JSON.stringify( { name: name, settings: settings || {} } );
		if ( Object.prototype.hasOwnProperty.call( cache, body ) ) {
			return Promise.resolve( cache[ body ] );
		}
		if ( pending[ body ] ) {
			return pending[ body ];
		}
		pending[ body ] = window
			.fetch( config.restUrl.replace( /\/$/, '' ) + '/render', {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
				body: body,
			} )
			.then( function ( res ) {
				return res.json().then( function ( json ) {
					if ( ! res.ok ) {
						throw new Error( ( json && json.message ) || res.statusText );
					}
					return json.html || '';
				} );
			} )
			.then( function ( html ) {
				cache[ body ] = html;
				delete pending[ body ];
				return html;
			} )
			.catch( function ( err ) {
				delete pending[ body ];
				throw err;
			} );
		return pending[ body ];
	}

	function settingFor( field, es ) {
		switch ( field.type ) {
			case 'select':
				return Object.assign( {}, es.SELECT, {
					options: Object.keys( field.options || {} ).map( function ( value ) {
						return { value: value, title: field.options[ value ] };
					} ),
				} );
			case 'toggle':
				return Object.assign( {}, es.TOGGLER );
			case 'number':
			case 'product':
				return Object.assign( {}, es.INPUT_NUMBER, { placeholder: String( field.default ) } );
			default:
				return Object.assign( {}, es.INPUT, { placeholder: field.default ? String( field.default ) : '' } );
		}
	}

	function defaults( def ) {
		var out = {};
		def.settings.forEach( function ( field ) {
			out[ field.key ] = field.default;
		} );
		return out;
	}

	function makeComponent( def ) {
		return forwardRef( function ( props, ref ) {
			var kirki = window.kirki;
			var useState = kirki.hooks.useState;
			var useEffect = kirki.hooks.useEffect;
			var element = kirki.getCanvasElement( props.elementId ) || {};
			var settings = ( element.properties && element.properties.settings ) || {};
			var key = JSON.stringify( settings );
			var state = useState( { html: null, error: null } );
			var view = state[ 0 ];
			var setView = state[ 1 ];

			useEffect(
				function () {
					var alive = true;
					var timer = window.setTimeout( function () {
						render( def.name, settings ).then(
							function ( html ) {
								if ( alive ) {
									setView( { html: html, error: null } );
								}
							},
							function ( err ) {
								if ( alive ) {
									setView( { html: null, error: err.message } );
								}
							}
						);
					}, 150 );
					return function () {
						alive = false;
						window.clearTimeout( timer );
					};
				},
				[ key ]
			);

			var attrs = Object.assign( {}, kirki.getAllAttributes ? kirki.getAllAttributes( element ) : {} );
			attrs.ref = ref;
			attrs.className = [ props.className || '', 'kwi-editor-host' ].join( ' ' ).trim();
			attrs[ 'data-kwi-editor' ] = def.slug;

			var inner;
			if ( view.error ) {
				inner = '<div class="kwi-placeholder kwi-placeholder--error">' + escapeHtml( def.title + ': ' + config.i18n.error + ' (' + view.error + ')' ) + '</div>';
			} else if ( null === view.html ) {
				inner = '<div class="kwi-placeholder kwi-placeholder--loading">' + escapeHtml( def.title + ' – ' + config.i18n.loading ) + '</div>';
			} else {
				inner = view.html;
			}
			attrs.dangerouslySetInnerHTML = { __html: inner };
			return h( 'div', attrs );
		} );
	}

	function escapeHtml( s ) {
		return String( s ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function icon() {
		return h(
			'svg',
			{ width: 24, height: 24, viewBox: '0 0 24 24', fill: 'none', xmlns: 'http://www.w3.org/2000/svg' },
			h( 'path', {
				d: 'M3 4h2l2.4 10.2a1 1 0 0 0 1 .8h8.9a1 1 0 0 0 1-.76L20 8H6.2',
				stroke: 'currentColor',
				strokeWidth: 1.6,
				strokeLinecap: 'round',
				strokeLinejoin: 'round',
			} ),
			h( 'circle', { cx: 9, cy: 19, r: 1.5, fill: 'currentColor' } ),
			h( 'circle', { cx: 17, cy: 19, r: 1.5, fill: 'currentColor' } )
		);
	}

	function register() {
		var kirki = window.kirki;
		var es = kirki.elementSettings || {};
		var registered = [];
		config.elements.forEach( function ( def ) {
			// Kirki title-cases category names (first letter upper, rest lower).
			var groupLabel = 'Woo ' + String( config.groups[ def.group ] || def.group ).toLowerCase();
			try {
				kirki.RegisterElement( {
					name: def.name,
					type: 'element',
					source: config.source,
					className: '',
					title: def.title,
					description: def.description,
					category: groupLabel,
					icon: icon(),
					visibility: true,
					reRenderOnElementChange: true,
					children: [],
					properties: { tag: def.tag, settings: defaults( def ) },
					constraints: { childrens: [ { element: '*', condition: 'FORBID' } ] },
					Component: makeComponent( def ),
					controls: { margin: true, padding: true, height: true, width: true },
					settings: def.settings.map( function ( field ) {
						return { key: field.key, label: field.label, setting: settingFor( field, es ) };
					} ),
				} );
				registered.push( def.name );
			} catch ( e ) {
				// eslint-disable-next-line no-console
				console.error( '[kirki-woo] could not register', def.name, e );
			}
		} );
		window.kirkiWooRegistered = registered;
		document.dispatchEvent( new CustomEvent( 'kirki-woo:registered', { detail: registered } ) );
	}

	// Kirki copies only theme assets into its canvas iframe; add ours.
	function injectCanvas() {
		var frames = document.querySelectorAll( 'iframe[data-kirki-canvas-iframe], iframe#iframe-kirki-canvas' );
		Array.prototype.forEach.call( frames, function ( frame ) {
			var doc;
			try {
				doc = frame.contentDocument;
			} catch ( e ) {
				return;
			}
			if ( ! doc || ! doc.head || ! doc.body || doc.getElementById( 'kirki-woo-canvas-styles' ) ) {
				return;
			}
			var marker = doc.createElement( 'meta' );
			marker.id = 'kirki-woo-canvas-styles';
			doc.head.appendChild( marker );
			( config.canvasStyles || [] ).forEach( function ( href ) {
				var link = doc.createElement( 'link' );
				link.rel = 'stylesheet';
				link.href = href;
				doc.head.appendChild( link );
			} );
			( config.canvasClasses || [] ).forEach( function ( c ) {
				doc.body.classList.add( c );
			} );
		} );
	}

	if ( ! config ) {
		return;
	}
	window.setInterval( injectCanvas, 1000 );
	var tries = 0;
	( function wait() {
		if ( window.kirki && typeof window.kirki.RegisterElement === 'function' && window.kirki.hooks ) {
			register();
			return;
		}
		if ( ++tries < 200 ) {
			window.setTimeout( wait, 50 );
		}
	} )();
} )();
