( function ( wp, cfg ) {
	if ( ! wp || ! cfg ) return;

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var TextControl = wp.components.TextControl;

	// Bindable attributes per block → which field list to offer.
	var BLOCKS = {
		'core/paragraph': { content: [ 'Text', 'all' ] },
		'core/heading': { content: [ 'Text', 'all' ] },
		'core/button': { text: [ 'Text', 'all' ], url: [ 'Link', 'url' ] },
		'core/image': { url: [ 'Image', 'media' ], alt: [ 'Alt', 'all' ] }
	};

	var LABELS = {};
	( cfg.all || [] ).forEach( function ( g ) {
		Object.keys( g.options ).forEach( function ( k ) { LABELS[ k ] = g.label + ' — ' + g.options[ k ]; } );
	} );

	function options( list ) {
		var out = [ { label: '— none —', value: '' } ];
		( cfg[ list ] || [] ).forEach( function ( g ) {
			Object.keys( g.options ).forEach( function ( k ) {
				out.push( { label: g.label + ' — ' + g.options[ k ], value: k } );
			} );
		} );
		return out;
	}

	function getBindings( attributes ) {
		return ( attributes.metadata && attributes.metadata.bindings ) || {};
	}

	// Editor preview of bound values (WP 6.7+).
	if ( wp.blocks.registerBlockBindingsSource ) {
		try {
			wp.blocks.registerBlockBindingsSource( {
				name: cfg.source,
				label: 'Gruppa',
				usesContext: [ 'postId', 'postType' ],
				getValues: function ( args ) {
					var out = {};
					Object.keys( args.bindings || {} ).forEach( function ( attr ) {
						var key = args.bindings[ attr ].args && args.bindings[ attr ].args.key;
						out[ attr ] = ( attr === 'url' || attr === 'id' ) ? undefined : '{' + ( LABELS[ key ] || key || 'Gruppa' ) + '}';
					} );
					return out;
				},
				canUserEditValue: function () { return false; }
			} );
		} catch ( e ) {}
	}

	var withGruppaPanel = wp.compose.createHigherOrderComponent( function ( BlockEdit ) {
		return function ( props ) {
			var map = BLOCKS[ props.name ];
			if ( ! map ) return el( BlockEdit, props );

			var bindings = getBindings( props.attributes );
			var first = null;
			Object.keys( map ).forEach( function ( a ) {
				if ( ! first && bindings[ a ] && bindings[ a ].source === cfg.source ) first = bindings[ a ].args || {};
			} );
			var srcState = wp.element.useState( ( first && first.source ) || 'current' );
			var itemState = wp.element.useState( ( first && first.item ) || '' );
			var itemSource = srcState[ 0 ];
			var itemValue = itemState[ 0 ];

			function write( attr, key, src, item ) {
				var next = Object.assign( {}, getBindings( props.attributes ) );
				var attrs = attr ? [ attr ] : Object.keys( map );
				attrs.forEach( function ( a ) {
					var k = attr ? key : ( next[ a ] && next[ a ].source === cfg.source ? next[ a ].args.key : '' );
					if ( ! k ) {
						if ( attr ) delete next[ a ];
						return;
					}
					var args = { key: k };
					if ( src && src !== 'current' ) {
						args.source = src;
						args.item = item;
					}
					next[ a ] = { source: cfg.source, args: args };
				} );
				var meta = Object.assign( {}, props.attributes.metadata || {} );
				if ( Object.keys( next ).length ) meta.bindings = next; else delete meta.bindings;
				props.setAttributes( { metadata: meta } );
			}

			var controls = Object.keys( map ).map( function ( attr ) {
				var b = bindings[ attr ];
				return el( SelectControl, {
					key: attr,
					label: map[ attr ][ 0 ],
					value: b && b.source === cfg.source ? b.args.key : '',
					options: options( map[ attr ][ 1 ] ),
					onChange: function ( v ) { write( attr, v, itemSource, itemValue ); },
					__nextHasNoMarginBottom: true
				} );
			} );

			controls.push( el( SelectControl, {
				key: '_source',
				label: 'Item',
				value: itemSource,
				options: [
					{ label: 'Current', value: 'current' },
					{ label: 'By ID', value: 'id' },
					{ label: 'By slug', value: 'slug' }
				],
				onChange: function ( v ) { srcState[ 1 ]( v ); write( null, null, v, itemValue ); },
				__nextHasNoMarginBottom: true
			} ) );

			if ( itemSource !== 'current' ) {
				controls.push( el( TextControl, {
					key: '_item',
					label: 'ID / slug',
					value: itemValue,
					onChange: function ( v ) { itemState[ 1 ]( v ); write( null, null, itemSource, v ); },
					__nextHasNoMarginBottom: true
				} ) );
			}

			return el( Fragment, null,
				el( BlockEdit, props ),
				el( InspectorControls, null,
					el( PanelBody, { title: 'Gruppa', initialOpen: !! first, className: 'gruppa-bindings-panel' }, controls )
				)
			);
		};
	}, 'withGruppaPanel' );

	wp.hooks.addFilter( 'editor.BlockEdit', 'gruppa/bindings-panel', withGruppaPanel );
} )( window.wp, window.gruppaBindings );
