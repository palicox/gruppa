/* Gruppa – živý náhľad v Prispôsobiť (rovnaký generátor ako inc/css.php) */
( function ( api, data ) {
	'use strict';

	var map = data.map;
	var timer;

	function get( id ) {
		var v = api.has( id ) ? api( id ).get() : '';
		return 'string' === typeof v ? v.trim() : '';
	}

	function focus( color ) {
		var m = /^#([0-9a-f]{3}|[0-9a-f]{6})$/i.exec( color );
		if ( m ) {
			var h = m[ 1 ];
			if ( 3 === h.length ) {
				h = h[ 0 ] + h[ 0 ] + h[ 1 ] + h[ 1 ] + h[ 2 ] + h[ 2 ];
			}
			return '0 0 0 3px rgba(' + parseInt( h.substr( 0, 2 ), 16 ) + ', ' + parseInt( h.substr( 2, 2 ), 16 ) + ', ' + parseInt( h.substr( 4, 2 ), 16 ) + ', 0.15)';
		}
		return '0 0 0 3px color-mix(in srgb, ' + color + ' 15%, transparent)';
	}

	function googleFamily( name, weights ) {
		return name.replace( / /g, '+' ) + ( weights ? ':wght@' + weights : '' );
	}

	function fonts() {
		var bodySrc = get( 'gruppa_font_body_src' ) || 'google';
		var bodyName = get( 'gruppa_font_body_name' );
		var headSrc = get( 'gruppa_font_head_src' ) || 'google';
		var headName = get( 'gruppa_font_head_name' );
		var family = function ( src, name ) {
			if ( 'system' === src ) {
				return data.stack;
			}
			return name ? "'" + name + "', " + data.stack : '';
		};
		var google = [];
		var bodyG = '';
		if ( 'google' === bodySrc ) {
			bodyG = bodyName || 'Google Sans Flex';
			google.push( googleFamily( bodyG, get( 'gruppa_font_body_w' ) || '100..900' ) );
		}
		if ( 'google' === headSrc && headName && headName !== bodyG ) {
			google.push( googleFamily( headName, get( 'gruppa_font_head_w' ) || '100..900' ) );
		}
		return {
			body: family( bodySrc, bodyName ),
			head: family( headSrc, headName ),
			url: google.length ? 'https://fonts.googleapis.com/css2?family=' + google.join( '&family=' ) + '&display=swap' : ''
		};
	}

	function build() {
		var root = [];
		var blocks = { '': {}, t: {}, d: {} };
		var order = { '': [], t: [], d: [] };
		var add = function ( mq, sel, decl ) {
			if ( ! blocks[ mq ][ sel ] ) {
				blocks[ mq ][ sel ] = [];
				order[ mq ].push( sel );
			}
			blocks[ mq ][ sel ].push( decl );
		};

		map.static.forEach( function ( s ) {
			add( '', s[ 0 ], s[ 1 ] );
		} );

		var f = fonts();
		if ( f.body ) {
			add( '', 'body', 'font-family:' + f.body );
		}
		if ( f.head ) {
			add( '', data.headings, 'font-family:' + f.head );
		}

		map.fields.forEach( function ( field ) {
			var v = get( field.id );
			if ( field[ 'var' ] ) {
				v = v || field.def;
				root.push( field[ 'var' ] + ':' + v );
				if ( field.focus ) {
					root.push( '--gruppa-focus:' + focus( v ) );
				}
				return;
			}
			if ( ! v && ! field.always ) {
				return;
			}
			v = v || field.def;
			if ( ! v ) {
				return;
			}
			field.css.forEach( function ( r ) {
				add( r[ 2 ] || '', r[ 0 ], r[ 1 ] + ':' + ( null != r[ 3 ] ? r[ 3 ] : v ) );
			} );
		} );

		var css = ':root{' + root.join( ';' ) + '}\n';
		[ [ '', '' ], [ 't', '(min-width: 768px)' ], [ 'd', '(min-width: 1024px)' ] ].forEach( function ( q ) {
			var rules = order[ q[ 0 ] ].map( function ( sel ) {
				return sel + '{' + blocks[ q[ 0 ] ][ sel ].join( ';' ) + '}\n';
			} ).join( '' );
			if ( rules ) {
				css += q[ 1 ] ? '@media ' + q[ 1 ] + '{\n' + rules + '}\n' : rules;
			}
		} );
		return { css: css, fonts: f.url };
	}

	function styleTag( id, after ) {
		var el = document.getElementById( id );
		if ( ! el ) {
			el = document.createElement( 'style' );
			el.id = id;
			( after && after.parentNode ? after.parentNode : document.head ).insertBefore( el, after ? after.nextSibling : null );
		}
		return el;
	}

	function render() {
		var out = build();
		styleTag( 'gruppa-inline-css', document.getElementById( 'gruppa-base-inline-css' ) ).textContent = out.css;

		var link = document.getElementById( 'gruppa-font-css' );
		if ( out.fonts ) {
			if ( ! link ) {
				link = document.createElement( 'link' );
				link.id = 'gruppa-font-css';
				link.rel = 'stylesheet';
				document.head.appendChild( link );
			}
			if ( link.getAttribute( 'href' ) !== out.fonts ) {
				link.setAttribute( 'href', out.fonts );
			}
		} else if ( link ) {
			link.parentNode.removeChild( link );
		}
	}

	function schedule() {
		clearTimeout( timer );
		timer = setTimeout( render, 60 );
	}

	var ids = map.fields.map( function ( f ) {
		return f.id;
	} ).concat( [ 'gruppa_font_body_src', 'gruppa_font_body_name', 'gruppa_font_body_w', 'gruppa_font_head_src', 'gruppa_font_head_name', 'gruppa_font_head_w' ] );

	ids.forEach( function ( id ) {
		api( id, function ( setting ) {
			setting.bind( schedule );
		} );
	} );

	// CSS témy
	api( 'gruppa_base_css', function ( setting ) {
		setting.bind( function ( value ) {
			styleTag( 'gruppa-base-inline-css' ).textContent = ( value && value.trim() ) ? value : data.themeCss;
		} );
	} );

} )( wp.customize, gruppaPreview );
