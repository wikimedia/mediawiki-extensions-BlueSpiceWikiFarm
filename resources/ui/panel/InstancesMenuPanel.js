require( './../widget/InstanceSectionWidget.js' );
require( './../widget/InstanceSkeletonSectionWidget.js' );
require( './../widget/SearchableInstanceSectionWidget.js' );

ext.bluespiceWikiFarm.ui.InstancesMenuPanel = function ( cfg ) {
	cfg = cfg || {};
	ext.bluespiceWikiFarm.ui.InstancesMenuPanel.parent.call( this, cfg );
	this.farmConfig = ext.bluespiceWikiFarm._config(); // eslint-disable-line no-underscore-dangle
	this.$element.addClass( 'card-body mega-menu-wrapper' );
	this.$quickAccess = $( '<div>' );
	this.$overview = $( '<div>' ).addClass( 'd-flex justify-content-center' );
	this.$element.append( this.$quickAccess );
	this.$element.append( this.$overview );
	this.makeQuickAccessPanel();
	this.makeFavouritesPanel();
	this.makePinnedPanel();
	this.makeOtherPanel();
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.InstancesMenuPanel, OO.ui.Widget );

ext.bluespiceWikiFarm.ui.InstancesMenuPanel.prototype.makeQuickAccessPanel = function () {
	const skeleton = new ext.bluespiceWikiFarm.ui.widget.InstanceSkeletonSectionWidget( {
		sectionId: 'context',
		count: 2
	} );
	this.$quickAccess.append( skeleton.$element );

	const api = new mw.Rest();
	api.get( '/bluespice/farm/v1/instances/context' ).done( ( result ) => {
		skeleton.$element.remove();
		const $contextCnt = $( '<div>' ).addClass( 'd-flex justify-content-center' );
		for ( const [ key, elements ] of Object.entries( result ) ) { // eslint-disable-line es-x/no-object-entries
			const section = new ext.bluespiceWikiFarm.ui.widget.InstanceSectionWidget( {
				sectionId: key,
				title: 'Schnellzugriff',
				elements: elements
			} );
			$contextCnt.append( section.$element );
		}
		this.$quickAccess.append( $contextCnt );
	} );
};

ext.bluespiceWikiFarm.ui.InstancesMenuPanel.prototype.makeFavouritesPanel = function () {
	this.$favourite = $( '<div>' ).addClass();
	this.$overview.append( this.$favourite );
	this.loadFavourites();
};

ext.bluespiceWikiFarm.ui.InstancesMenuPanel.prototype.loadFavourites = function () {
	this.$favourite.empty();
	const skeleton = new ext.bluespiceWikiFarm.ui.widget.InstanceSkeletonSectionWidget( {
		sectionId: 'favourites',
		count: 10
	} );
	this.$favourite.append( skeleton.$element );

	const api = new mw.Rest();
	api.get( '/bluespice/farm/v1/instances/list', {
		limit: 20,
		sort: JSON.stringify( [ { property: 'title', direction: 'asc' } ] ),
		filter: JSON.stringify( [ {
			property: 'favourite',
			value: true,
			operator: 'eq',
			type: 'boolean'
		} ] )
	} ).done( ( result ) => {
		skeleton.$element.remove();
		if ( result.results.length === 0 ) {
			return;
		}
		const section = new ext.bluespiceWikiFarm.ui.widget.InstanceSectionWidget( {
			sectionId: 'favourites',
			title: 'Favourites',
			elements: result.results,
			sectionHasFavourite: true
		} );
		section.connect( this, { favoured: 'loadFavourites' } );
		this.$favourite.append( section.$element );
	} );
};

ext.bluespiceWikiFarm.ui.InstancesMenuPanel.prototype.makePinnedPanel = function () {
	this.$pinned = $( '<div>' ).addClass();
	this.$overview.append( this.$pinned );
	const skeleton = new ext.bluespiceWikiFarm.ui.widget.InstanceSkeletonSectionWidget( {
		sectionId: 'pinned',
		count: 10
	} );
	this.$pinned.append( skeleton.$element );

	const api = new mw.Rest();
	api.get( '/bluespice/farm/v1/instances/pinned' ).done( ( results ) => {
		skeleton.$element.remove();
		if ( results.length === 0 ) {
			return;
		}
		const section = new ext.bluespiceWikiFarm.ui.widget.InstanceSectionWidget( {
			sectionId: 'pinned',
			title: 'Pinned',
			elements: results
		} );
		this.$pinned.append( section.$element );
	} );
};

ext.bluespiceWikiFarm.ui.InstancesMenuPanel.prototype.makeOtherPanel = function () {
	this.$other = $( '<div>' ).addClass();
	this.$overview.append( this.$other );
	const skeleton = new ext.bluespiceWikiFarm.ui.widget.InstanceSkeletonSectionWidget( {
		sectionId: 'other',
		hasSearch: true,
		count: 5
	} );
	this.$other.append( skeleton.$element );

	// ToDO: latest visited should be added here as default - currently empty
	skeleton.$element.remove();
	const section = new ext.bluespiceWikiFarm.ui.widget.SearchableInstanceSectionWidget( {
		sectionId: 'other',
		title: 'Other',
		elements: []
	} );
	section.connect( this, { favoured: 'loadFavourites' } );
	this.$other.append( section.$element );
};
