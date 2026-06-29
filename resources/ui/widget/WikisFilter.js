ext.bluespiceWikiFarm.ui.widget.WikisFilter = function ( cfg ) {
	cfg = cfg || {};
	this.options = cfg.options || [];
	this.context = cfg.context || null;
	this.lookup = cfg.lookup || null;
	this.config = ext.bluespiceWikiFarm._config(); // eslint-disable-line no-underscore-dangle

	ext.bluespiceWikiFarm.ui.widget.WikisFilter.parent.call( this, cfg );

	this.$element.addClass( 'bs-extendedsearch-filter-instance-widget' );

	this.visibleFilter = [];
	this.localWiki = this.options
		.filter( ( item ) => item.data === this.config.instanceId );
	if ( this.localWiki ) {
		this.localWiki = this.localWiki[ 0 ];
	}

	let selected = [];
	if ( this.context ) {
		selected = this.context.definition.searchInWikis;
		if ( selected.length === 1 && selected[ 0 ] === '*' ) {
			selected = [];
		}
		const index = selected.indexOf( this.localWiki.data );
		if ( index !== -1 ) {
			this.localWiki.selected = true;
			selected.splice( index, 1 );
		} else {
			this.localWiki.selected = false;
		}
	}
	this.visibleFilter.push( this.localWiki );

	for ( const i in this.options ) {
		if ( selected.includes( this.options[ i ].data ) ) {
			this.options[ i ].selected = true;
			this.visibleFilter.push( this.options[ i ] );
		} else {
			if ( this.localWiki.data === this.options[ i ].data ) {
				this.options[ i ].selected = this.localWiki.selected;
				continue;
			}
			this.options[ i ].selected = false;
		}
	}

	this.filter = new OOJSPlus.ui.widget.FilterBarWidget( {
		noFilterActiveLabel: mw.message( 'wikifarm-search-filter-show-all-label' ).text(),
		visibleFilter: this.visibleFilter,
		filterElements: this.options,
		allowUnselect: true,
		multiSelect: true,
		selected: selected
	} );
	this.filter.connect( this, {
		select: 'updateLookup',
		unselect: 'updateLookup',
		clear: () => {
			this.lookup.setContext( {
				key: 'farm-global',
				definition: { searchInWikis: [ '*' ] },
				showCustomPill: false
			} );
			bs.extendedSearch.SearchCenter.updateQueryHash();
		}
	} );

	this.$element.append( this.filter.$element );
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.widget.WikisFilter, OO.ui.Widget );

ext.bluespiceWikiFarm.ui.widget.WikisFilter.prototype.updateLookup = function () {
	let value = this.filter.getSelected();
	if ( value.length === 0 ) {
		value = [ '*' ];
	}
	this.lookup.setContext( {
		key: 'farm-global',
		definition: { searchInWikis: value },
		showCustomPill: false
	} );
	this.lookup.setFrom( 0 );
	bs.extendedSearch.SearchCenter.updateQueryHash();
};
