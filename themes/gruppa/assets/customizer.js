/* Gruppa – ovládacie prvky v Prispôsobiť */
( function ( $, api ) {
	'use strict';

	function set( id, value ) {
		if ( api.has( id ) ) {
			api( id ).set( value );
		}
	}

	function swatch( $wrap, value ) {
		var $sw = $wrap.find( '.gruppa-swatch' );
		var color = value || $sw.data( 'default' ) || 'transparent';
		if ( /^var\(/.test( color ) ) {
			color = $sw.data( 'default' ) || 'transparent';
		}
		$sw.css( 'background-color', color );
		if ( /^#[0-9a-f]{6}$/i.test( color ) ) {
			$sw.find( 'input[type="color"]' ).val( color );
		}
	}

	// Text, select, písmo, box
	$( document ).on( 'input change', '.gruppa-in', function () {
		var $el = $( this );
		set( $el.data( 'setting' ), $.trim( $el.val() ) );
		if ( $el.closest( '.gruppa-color' ).length ) {
			swatch( $el.closest( '.gruppa-color' ), $.trim( $el.val() ) );
		}
	} );

	// Natívny výber farby → textové pole
	$( document ).on( 'input change', '.gruppa-swatch input[type="color"]', function () {
		$( this ).closest( '.gruppa-color' ).find( '.gruppa-in' ).val( this.value.toUpperCase() ).trigger( 'input' );
	} );

	// Číslo + jednotka → jedna hodnota (2.5rem)
	$( document ).on( 'input change', '.gruppa-unit .gruppa-num, .gruppa-unit .gruppa-u', function () {
		var $wrap = $( this ).closest( '.gruppa-unit' );
		var num = $.trim( $wrap.find( '.gruppa-num' ).val() );
		set( $wrap.data( 'setting' ), '' === num ? '' : num + $wrap.find( '.gruppa-u' ).val() );
	} );

	// CSS témy – širší panel pre editor
	api.bind( 'ready', function () {
		api.section( 'gruppa_css', function ( section ) {
			section.expanded.bind( function ( open ) {
				$( document.body ).toggleClass( 'gruppa-wide', open );
			} );
		} );
	} );

} )( jQuery, wp.customize );
