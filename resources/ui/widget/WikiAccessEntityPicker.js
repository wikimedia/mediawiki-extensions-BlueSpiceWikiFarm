ext.bluespiceWikiFarm.ui.widget.WikiAccessEntityPicker = function ( cfg ) {
	cfg = cfg || {};
	ext.bluespiceWikiFarm.ui.widget.WikiAccessEntityPicker.parent.call( this, cfg );
	this.selectedItem = null;

	this.connect( this, {
		change: 'onChange'
	} );
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.widget.WikiAccessEntityPicker, OOJSPlus.ui.widget.UserPickerWidget );

ext.bluespiceWikiFarm.ui.widget.WikiAccessEntityPicker.prototype.makeLookup = function ( data ) {
	const dfd = $.Deferred();

	const promises = [
		mws.commonwebapis.user.query( data ),
		$.ajax( {
			method: 'GET',
			url: mw.util.wikiScript( 'rest' ) + '/bluespice/farm/v1/teams',
			data: { query: data.query || '' },
			dataType: 'json',
			contentType: 'application/json; charset=UTF-8'
		} )
	];
	$.when.apply( $, promises ).done( ( userData, teamData ) => {
		const data = { team: [], user: [] }; // eslint-disable-line no-shadow
		for ( let i = 0; i < userData.length; i++ ) {
			data.user.push( {
				data: userData[ i ],
				label: userData[ i ].display_name
			} );
		}
		for ( let i = 0; i < teamData[ 0 ].results.length; i++ ) {
			data.team.push( {
				icon: 'userGroup',
				data: { entityType: 'team', entityKey: teamData[ 0 ].results[ i ].name },
				label: teamData[ 0 ].results[ i ].name
			} );
		}
		dfd.resolve( data );
	} ).fail( () => {
		dfd.reject();
	} );
	return dfd.promise();
};

ext.bluespiceWikiFarm.ui.widget.WikiAccessEntityPicker.prototype.getLookupMenuOptionsFromData = function ( data ) {
	let i;
	const items = [];

	for ( const group in data ) {
		if ( !data.hasOwnProperty( group ) ) {
			continue;
		}
		if ( data[ group ].length === 0 ) {
			continue;
		}
		items.push( new OO.ui.MenuSectionOptionWidget( {
			// * wikifarm-ui-access-assignee-type-user
			// * wikifarm-ui-access-assignee-type-team
			label: mw.msg( 'wikifarm-ui-access-assignee-type-' + group )
		} ) );
		for ( i = 0; i < data[ group ].length; i++ ) {
			if ( group === 'user' ) {
				items.push(
					new OOJSPlus.ui.widget.UserMenuOptionWidget( data[ group ][ i ].data )
				);
			} else {
				items.push( new OO.ui.MenuOptionWidget( data[ group ][ i ] ) );
			}
		}
	}

	return items;
};

ext.bluespiceWikiFarm.ui.widget.WikiAccessEntityPicker.prototype.focus = function () {
	return this;
};

ext.bluespiceWikiFarm.ui.widget.WikiAccessEntityPicker.prototype.onLookupMenuChoose = function ( item ) {
	this.closeLookupMenu();
	this.setLookupsDisabled( true );
	if ( item instanceof OOJSPlus.ui.widget.UserMenuOptionWidget ) {
		this.setValue( mw.msg( 'wikifarm-ui-access-assignee-type-user' ) + ': ' + item.getDisplayName() );
		this.selectedItem = {
			entityType: 'user',
			entityKey: item.userWidget.user.user_name
		};
	} else {
		this.setValue( mw.msg( 'wikifarm-ui-access-assignee-type-team' ) + ': ' + item.getLabel() );
		this.selectedItem = item.getData();
	}
	this.emit( 'choose', this.selectedItem );
	this.setLookupsDisabled( false );
};

ext.bluespiceWikiFarm.ui.widget.WikiAccessEntityPicker.prototype.setValue = function ( value ) {
	// Calling parent of a parent on purpose!
	OOJSPlus.ui.widget.UserPickerWidget.parent.prototype.setValue.call( this, value );
};

ext.bluespiceWikiFarm.ui.widget.WikiAccessEntityPicker.prototype.getSelectedItem = function () {
	return this.selectedItem;
};

ext.bluespiceWikiFarm.ui.widget.WikiAccessEntityPicker.prototype.onChange = function () {
	this.selectedItem = null;
};
