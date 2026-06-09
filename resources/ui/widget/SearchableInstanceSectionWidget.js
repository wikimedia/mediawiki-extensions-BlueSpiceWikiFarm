require( './InstanceSectionWidget.js' );

ext.bluespiceWikiFarm.ui.widget.SearchableInstanceSectionWidget = function ( cfg ) {
	ext.bluespiceWikiFarm.ui.widget.SearchableInstanceSectionWidget.super.call( this, cfg );
	this.farmConfig = ext.bluespiceWikiFarm._config(); // eslint-disable-line no-underscore-dangle
	this.buildSearch();
};

OO.inheritClass(
	ext.bluespiceWikiFarm.ui.widget.SearchableInstanceSectionWidget,
	ext.bluespiceWikiFarm.ui.widget.InstanceSectionWidget
);

ext.bluespiceWikiFarm.ui.widget.SearchableInstanceSectionWidget.prototype.buildSearch = function () {
	this.searchInput = new OO.ui.SearchInputWidget( {
		placeholder: mw.msg( 'wikifarm-instances-search-placeholder' ),
		icon: 'search',
		classes: [ 'farm-wikis-section-search' ]
	} );

	this.searchInput.on(
		'change',
		OO.ui.debounce( this.onSearchChange.bind( this ), 200 )
	);

	this.$header.after( new OO.ui.FieldLayout( this.searchInput,
		{
			label: mw.msg( 'wikifarm-instances-menu-search-label' ),
			invisibleLabel: true
		} ).$element );
};

ext.bluespiceWikiFarm.ui.widget.SearchableInstanceSectionWidget.prototype.onSearchChange = function ( value ) {
	const query = value.toLowerCase().trim();

	if ( this.$searchResultSection ) {
		this.$searchResultSection.remove();
		this.$searchResultSection = null;
	}

	if ( this.allResultsLink ) {
		this.allResultsLink.$element.detach();
	}

	if ( query.length === 0 ) {
		return;
	}

	const api = new mw.Rest();
	api.get( '/bluespice/farm/v1/instances/list', {
		limit: 5,
		query: query
	} ).done( ( result ) => {
		if ( this.$searchResultSection ) {
			this.$searchResultSection.remove();
		}
		const section = new ext.bluespiceWikiFarm.ui.widget.InstanceSectionWidget( {
			sectionId: 'results',
			title: '',
			elements: result.results,
			sectionHasFavourite: true
		} );
		section.connect( this, { favoured: [ 'emit', 'favoured' ] } );
		this.$searchResultSection = section.$element;
		this.$element.append( section.$element );

		if ( result.total >= 5 ) {
			this.showAllResultsLabel( query, result.total );
		}
	} );
};

ext.bluespiceWikiFarm.ui.widget.SearchableInstanceSectionWidget.prototype.getWikisSpecialPageUrl = function ( query ) {
	if ( this.farmConfig.instanceId === 'w' ) {
		return mw.util.getUrl( 'Special:Wikis', { query: query } );
	}
	return mw.config.get( 'wgServer' ) + '/wiki/Special:Wikis?query=' + encodeURIComponent( query );
};

ext.bluespiceWikiFarm.ui.widget.SearchableInstanceSectionWidget.prototype.showAllResultsLabel = function ( query, numberOfResults ) {
	const url = this.getWikisSpecialPageUrl( query );

	if ( !this.allResultsLink ) {
		const config = {
			href: url,
			label: mw.message( 'wikifarm-instances-menu-search-more-results-label', numberOfResults ).text()
		};
		if ( this.farmConfig.instanceId !== 'w' ) {
			config.target = '_blank';
		}
		this.allResultsLink = new OOJSPlus.ui.widget.LinkWidget( config );
	} else {
		this.allResultsLink.$link.attr( 'href', url );
	}

	this.$element.append( this.allResultsLink.$element );
};
