/* eslint-disable no-underscore-dangle */
ext.bluespiceWikiFarm.ui.widget.CombinedTitleInputWidget = function ( cfg ) {
	cfg = cfg || {};
	ext.bluespiceWikiFarm.ui.widget.CombinedTitleInputWidget.parent.call( this, cfg );
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.widget.CombinedTitleInputWidget, OOJSPlus.ui.widget.TitleInputWidget );

ext.bluespiceWikiFarm.ui.widget.CombinedTitleInputWidget.prototype.makeLookup = function ( query, data ) {
	const dfd = $.Deferred(),
		params = Object.assign( data || {}, { query: query } );
	$.ajax( {
		method: 'GET',
		url: this.getUrl(),
		data: params
	} ).done( ( response ) => {
		if ( response && response.results ) {
			dfd.resolve( response.results );
		} else {
			dfd.resolve( [] );
		}
	} ).fail( ( err ) => {
		dfd.resolve( err );
	} );
	return dfd.promise();
};

ext.bluespiceWikiFarm.ui.widget.CombinedTitleInputWidget.prototype.getUrl = function () {
	return mw.util.wikiScript( 'rest' ) + '/bluespice/farm/v1/combined-title-query-store';
};

ext.bluespiceWikiFarm.ui.widget.CombinedTitleInputWidget.prototype.getLookupMenuOptionsFromData = function ( data ) {
	let i;
	let dataItem;
	const items = [];

	const grouped = this.group( data );
	for ( const group in grouped[ 0 ] ) {
		if ( !grouped[ 0 ].hasOwnProperty( group ) ) {
			continue;
		}
		items.push( new OO.ui.MenuSectionOptionWidget( {
			label: grouped[ 1 ][ group ]
		} ) );
		for ( i = 0; i < grouped[ 0 ][ group ].length; i++ ) {
			dataItem = grouped[ 0 ][ group ][ i ];
			items.push( new OO.ui.MenuOptionWidget( this.getDataItemForOption( dataItem ) ) );
		}
	}

	return items;
};

ext.bluespiceWikiFarm.ui.widget.CombinedTitleInputWidget.prototype.getDataItemForOption = function ( dataItem ) {
	return {
		label: dataItem.prefixed,
		data: dataItem
	};
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
