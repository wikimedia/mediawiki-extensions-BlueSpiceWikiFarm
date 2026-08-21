/* eslint-disable no-underscore-dangle */
ext.bluespiceWikiFarm.ui.widget.CombinedTitleInputWidget = function ( cfg ) {
	cfg = cfg || {};
	this.localInstanceOnly = cfg.localInstanceOnly || false;
	ext.bluespiceWikiFarm.ui.widget.CombinedTitleInputWidget.parent.call( this, cfg );
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.widget.CombinedTitleInputWidget, OOJSPlus.ui.widget.TitleInputWidget );

ext.bluespiceWikiFarm.ui.widget.CombinedTitleInputWidget.prototype.makeLookup = function ( query, data ) {
	const dfd = $.Deferred(),
		params = Object.assign( {}, data || {}, { query: query, limit: 25 } );
	const req = $.ajax( {
		url: this.getUrl(),
		data: params
	} ).done( ( response ) => {
		if ( response && response.results ) {
			dfd.resolve( response.results );
		} else {
			dfd.resolve( [] );
		}
	} ).fail( ( _jqXHR, textStatus ) => {
		if ( textStatus === 'abort' ) {
			dfd.reject();
			return;
		}
		dfd.resolve( [] );
	} );
	return dfd.promise( {
		abort: function () {
			req.abort();
		}
	} );
};

ext.bluespiceWikiFarm.ui.widget.CombinedTitleInputWidget.prototype.getUrl = function () {
	return mw.util.wikiScript( 'rest' ) + '/bluespice/farm/v1/combined-title-query-store';
};

ext.bluespiceWikiFarm.ui.widget.CombinedTitleInputWidget.prototype.getLookupMenuOptionsFromData = function ( data ) {
	let i;
	let dataItem;
	const items = [];
	if ( !Array.isArray( data ) ) {
		return items;
	}

	const grouped = this.group( data );
	for ( const group in grouped[ 0 ] ) {
		if ( !grouped[ 0 ].hasOwnProperty( group ) ) {
			continue;
		}
		const first = grouped[ 0 ][ group ][ 0 ];
		const color = this.getInstanceColor( first );
		const section = new OO.ui.MenuSectionOptionWidget( {
			label: this.getInstanceChip( grouped[ 1 ][ group ], first ),
			classes: [ 'wikifarm-combined-title-section' ]
		} );
		section.$element.css( '--wiki-color', color );
		items.push( section );
		for ( i = 0; i < grouped[ 0 ][ group ].length; i++ ) {
			dataItem = grouped[ 0 ][ group ][ i ];
			const option = this.getMenuOption( {
				label: null,
				data: dataItem
			} );
			option.$element
				.addClass( 'wikifarm-combined-title-option' )
				.css( {
					'--wiki-color': color,
					'--wiki-color-tint': this.getInstanceTint( color )
				} );
			items.push( option );
		}
	}
	if ( items.length === 0 && !this.mustExist ) {
		items.push( this.getMenuOption( {
			label: this.getRawValue(),
			data: { prefixed: this.getRawValue(), missing: true, _is_local_instance: true } // eslint-disable-line camelcase
		} ) );
	}

	return items;
};

/**
 * Header of a result group: the wiki name on a chip in the full color of the wiki, so that
 * the wikis are told apart at a glance, even where their colors are close to each other.
 *
 * @param {string} label Name of the wiki
 * @param {Object} dataItem Any result of that wiki
 * @return {jQuery}
 */
ext.bluespiceWikiFarm.ui.widget.CombinedTitleInputWidget.prototype.getInstanceChip = function ( label, dataItem ) {
	const $chip = $( '<span>' )
		.addClass( 'wikifarm-combined-title-chip' )
		.text( label );
	if ( dataItem && dataItem._instance_light_text === false ) {
		$chip.addClass( 'wikifarm-combined-title-chip--dark-text' );
	}
	return $chip;
};

/**
 * @param {Object} dataItem
 * @return {string}
 */
ext.bluespiceWikiFarm.ui.widget.CombinedTitleInputWidget.prototype.getInstanceColor = function ( dataItem ) {
	return ( dataItem && dataItem._instance_color ) || '#747474';
};

/**
 * @param {string} color Hex color, "#abc" or "#aabbcc"
 * @return {string} CSS color
 */
ext.bluespiceWikiFarm.ui.widget.CombinedTitleInputWidget.prototype.getInstanceTint = function ( color ) {
	let hex = ( color || '' ).replace( '#', '' );
	if ( hex.length === 3 ) {
		hex = hex[ 0 ] + hex[ 0 ] + hex[ 1 ] + hex[ 1 ] + hex[ 2 ] + hex[ 2 ];
	}
	if ( !/^[0-9a-fA-F]{6}$/.test( hex ) ) {
		return 'transparent';
	}
	const rgb = [
		parseInt( hex.slice( 0, 2 ), 16 ),
		parseInt( hex.slice( 2, 4 ), 16 ),
		parseInt( hex.slice( 4, 6 ), 16 )
	];
	return 'rgba( ' + rgb.join( ', ' ) + ', 0.16 )';
};

ext.bluespiceWikiFarm.ui.widget.CombinedTitleInputWidget.prototype.getDataItemForOption = function ( dataItem ) {
	return {
		label: dataItem.prefixed,
		data: dataItem
	};
};

ext.bluespiceWikiFarm.ui.widget.CombinedTitleInputWidget.prototype.extendFilters = function ( filters ) {
	if ( !this.localInstanceOnly ) {
		return filters;
	}
	filters.push( {
		type: 'list',
		value: [ '_local' ],
		operator: 'in',
		property: 'wiki_id'
	} );
	return filters;
};

ext.bluespiceWikiFarm.ui.widget.CombinedTitleInputWidget.prototype.group = function ( data ) {
	let grouped = {};
	const groupLabels = {};
	let local = null;
	let i;
	for ( i = 0; i < data.length; i++ ) {
		if ( !data[ i ].hasOwnProperty( '_instance' ) ) {
			continue;
		}
		if ( data[ i ]._is_local_instance ) {
			local = data[ i ]._instance;
		}

		if ( !grouped.hasOwnProperty( data[ i ]._instance ) ) {
			grouped[ data[ i ]._instance ] = [];
			groupLabels[ data[ i ]._instance ] = local === data[ i ]._instance ?
				mw.msg( 'wikifarm-widget-combined-title-input-local' ) :
				data[ i ]._instance_display;
		}
		grouped[ data[ i ]._instance ].push( data[ i ] );
	}

	// Sort, if name is same as "local" instance, put it on top
	if ( local ) {
		const localGroup = grouped[ local ];
		delete grouped[ local ];
		grouped = Object.assign( { [ local ]: localGroup }, grouped );
	}

	return [ grouped, groupLabels ];
};

ext.bluespiceWikiFarm.ui.widget.CombinedTitleInputWidget.prototype.getTitleKeyForLinking = function () {
	if ( !this.selectedTitle ) {
		return null;
	}
	return this.selectedTitle._is_local_instance ?
		this.selectedTitle.prefixed :
		this.selectedTitle._instance + ':' + this.selectedTitle.prefixed;
};

ext.bluespiceWikiFarm.ui.widget.CombinedTitleInputWidget.prototype.focus = function () {
	return this;
};
