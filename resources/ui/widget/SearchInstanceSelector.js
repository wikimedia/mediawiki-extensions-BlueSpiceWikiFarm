ext.bluespiceWikiFarm.ui.widget.SearchInstanceSelector = function ( cfg ) {
	cfg = cfg || {};
	this.options = cfg.options || [];
	this.context = cfg.context || null;
	this.lookup = cfg.lookup || null;

	this.buildMenu();
	cfg.popup = {
		$content: this.menuPanel.$element,
		align: 'forwards',
		padded: false
	};

	ext.bluespiceWikiFarm.ui.widget.SearchInstanceSelector.parent.call( this, cfg );
	bs.extendedSearch.mixin.FilterRemoveButton.call( this, { showRemove: true } );
	this.$element.append( this.$removeButton );

	this.$element.addClass( 'oo-ui-popupButtonWidget bs-extendedsearch-filter-button-widget' );
	this.$button.addClass( 'bs-extendedsearch-filter-button-button' );

	if ( this.context ) {
		this.setFromContext();
	} else {
		this.value = [ '_local' ];
		this.localButton.$element.addClass( 'option-selected' );
		this.doSetLabel();
	}
	setTimeout( () => {
		this.selector.connect( this, { change: 'onSelectorChange' } );
	}, 1 );

};

OO.inheritClass( ext.bluespiceWikiFarm.ui.widget.SearchInstanceSelector, OO.ui.PopupButtonWidget );
OO.mixinClass( ext.bluespiceWikiFarm.ui.widget.SearchInstanceSelector, bs.extendedSearch.mixin.FilterRemoveButton );

ext.bluespiceWikiFarm.ui.widget.SearchInstanceSelector.prototype.removeFilter = function () {
	this.selector.setValue( [ '_local' ] );
	this.updateLookup();
};

ext.bluespiceWikiFarm.ui.widget.SearchInstanceSelector.prototype.buildMenu = function () {
	this.availableValues = Object.assign( {
		_local: mw.message( 'wikifarm-ui-search-instance-filter-local' ).text(),
		_all: mw.message( 'wikifarm-ui-search-instance-filter-all' ).text()
	}, this.options );

	this.localButton = new OO.ui.ButtonWidget( {
		label: this.availableValues._local, // eslint-disable-line no-underscore-dangle
		framed: false,
		classes: [ 'wikifarm-search-instance-filter-button' ],
		flags: [ 'progressive' ]
	} );
	this.allButton = new OO.ui.ButtonWidget( {
		label: this.availableValues._all, // eslint-disable-line no-underscore-dangle
		framed: false,
		classes: [ 'wikifarm-search-instance-filter-button' ],
		flags: [ 'progressive' ]
	} );
	this.localButton.connect( this, { click: 'onLocalClick' } );
	this.allButton.connect( this, { click: 'onAllClick' } );

	const options = [];
	for ( const key in this.options ) {
		options.push( {
			data: key,
			label: this.options[ key ]
		} );
	}
	this.selectorLabel = new OO.ui.LabelWidget( {
		label: mw.msg( 'wikifarm-ui-search-instance-filter-hint' ),
		classes: [ 'wikifarm-search-instance-filter-hint' ]
	} );
	this.selector = new OO.ui.CheckboxMultiselectInputWidget( {
		options: options
	} );

	this.selectorFilter = new OO.ui.SearchInputWidget( {
		placeholder: mw.msg( 'wikifarm-ui-search-instance-filter-search' ),
		classes: [ 'wikifarm-search-instance-filter-search' ]
	} );
	this.selectorFilter.connect( this, {
		change: function () {
			// Hide items that do not match query from
			// the dropdown
			const query = this.selectorFilter.getValue().toLowerCase();
			this.selector.$element.find(
				'.oo-ui-checkboxMultioptionWidget:not(.oo-ui-checkboxMultioptionWidget-selected) .oo-ui-labelElement-label'
			).each( function () {
				const $this = $( this );
				if ( $this.text().toLowerCase().indexOf( query ) !== -1 ) {
					$this.parent().show();
				} else {
					$this.parent().hide();
				}
			} );
		}
	} );

	this.menuPanel = new OO.ui.PanelLayout( {
		expanded: false,
		padded: false
	} );
	this.optionsPanel = new OO.ui.PanelLayout( {
		expanded: false,
		padded: true,
		classes: [ 'wikifarm-search-instance-filter-options' ]
	} );

	this.optionsPanel.$element.append(
		this.selectorLabel.$element, this.selectorFilter.$element, this.selector.$element
	);
	this.menuPanel.$element.append(
		this.allButton.$element, this.localButton.$element,
		this.optionsPanel.$element
	);
};

ext.bluespiceWikiFarm.ui.widget.SearchInstanceSelector.prototype.setFromContext = function () {
	const searchInWikis = this.context.definition.searchInWikis || [ '_local' ];
	if ( searchInWikis.length === 1 && searchInWikis[ 0 ] === '*' ) {
		this.value = [ '_all' ];
		this.allButton.$element.addClass( 'option-selected' );
	} else if ( searchInWikis.length === 1 && searchInWikis[ 0 ] === '_local' ) {
		this.value = [ '_local' ];
		this.localButton.$element.addClass( 'option-selected' );
	} else {
		this.value = [ '_local' ].concat( searchInWikis );
		this.localButton.$element.addClass( 'option-selected' );
		this.selector.setValue( searchInWikis );
	}
	this.doSetLabel();
};

ext.bluespiceWikiFarm.ui.widget.SearchInstanceSelector.prototype.doSetLabel = function () {
	const valueLabel = [];
	for ( let i = 0; i < this.value.length; i++ ) {
		valueLabel.push( this.availableValues[ this.value[ i ] ] );
	}
	this.setLabel(
		mw.msg( 'wikifarm-ui-search-instance-filter', valueLabel.join( ', ' ) )
	);
};

ext.bluespiceWikiFarm.ui.widget.SearchInstanceSelector.prototype.updateLookup = function () {
	const value = this.value || [];
	if ( value.length === 0 || ( value.length === 1 && value[ 0 ] === '_local' ) ) {
		this.lookup.setContext( null );
	} else {
		let searchInWikis = value;
		if ( value.length === 1 && value[ 0 ] === '_all' ) {
			searchInWikis = [ '*' ];
		}
		this.lookup.setContext( {
			key: 'farm-global',
			definition: { searchInWikis: searchInWikis },
			showCustomPill: false
		} );
	}
	this.lookup.setFrom( 0 );
	bs.extendedSearch.SearchCenter.updateQueryHash();
};

ext.bluespiceWikiFarm.ui.widget.SearchInstanceSelector.prototype.onLocalClick = function () {
	this.value = [ '_local' ];
	this.updateLookup();
};

ext.bluespiceWikiFarm.ui.widget.SearchInstanceSelector.prototype.onAllClick = function () {
	this.value = [ '_all' ];
	this.updateLookup();
};

ext.bluespiceWikiFarm.ui.widget.SearchInstanceSelector.prototype.onSelectorChange = function ( value ) {
	if ( !value ) {
		return;
	}
	this.value = value;
	this.updateLookup();
};
