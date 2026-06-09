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
	this.addSpecialPageLink();
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.InstancesMenuPanel, OO.ui.Widget );

ext.bluespiceWikiFarm.ui.InstancesMenuPanel.prototype.makeQuickAccessPanel = function () {
	const skeleton = this.getSkeleton( 'context', 2, false );
	this.$quickAccess.append( skeleton.$element );

	const api = new mw.Rest();
	api.get( '/bluespice/farm/v1/instances/context' ).done( ( result ) => {
		skeleton.$element.remove();
		const $contextCnt = $( '<div>' ).addClass( 'd-flex justify-content-center' );
		for ( const [ key, elements ] of Object.entries( result ) ) { // eslint-disable-line es-x/no-object-entries
			// The following messages are used here:
			// * wikifarm-instances-menu-section-current
			// * wikifarm-instances-menu-section-quickaccess
			const section = new ext.bluespiceWikiFarm.ui.widget.InstanceSectionWidget( {
				sectionId: key,
				title: mw.message( 'wikifarm-instances-menu-section-' + key ).text(),
				elements: elements
			} );
			$contextCnt.append( section.$element );
		}
		this.$quickAccess.append( $contextCnt );
	} );
};

ext.bluespiceWikiFarm.ui.InstancesMenuPanel.prototype.makeFavouritesPanel = function () {
	this.$favourite = $( '<div>' );
	this.$overview.append( this.$favourite );
	this.loadFavourites();
};

ext.bluespiceWikiFarm.ui.InstancesMenuPanel.prototype.loadFavourites = function () {
	this.$favourite.empty();
	const skeleton = this.getSkeleton( 'favourites', 10, false );
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
		const section = new ext.bluespiceWikiFarm.ui.widget.InstanceSectionWidget( {
			sectionId: 'favourites',
			title: mw.message( 'wikifarm-instances-menu-section-favourites' ).text(),
			elements: result.results,
			sectionHasFavourite: true,
			emptyLabel: mw.message( 'wikifarm-instances-menu-empty-favorite-text' ).text()
		} );
		section.connect( this, { favoured: 'loadFavourites' } );
		this.$favourite.append( section.$element );
	} );
};

ext.bluespiceWikiFarm.ui.InstancesMenuPanel.prototype.makePinnedPanel = function () {
	this.$pinned = $( '<div>' );
	this.$overview.append( this.$pinned );
	const skeleton = this.getSkeleton( 'pinned', 10, false );
	this.$pinned.append( skeleton.$element );

	const api = new mw.Rest();
	api.get( '/bluespice/farm/v1/instances', {
		limit: 20,
		sort: JSON.stringify( [ { property: 'title', direction: 'asc' } ] ),
		filter: JSON.stringify( [ {
			property: 'pinned',
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
			sectionId: 'pinned',
			title: mw.message( 'wikifarm-instances-menu-section-featured' ).text(),
			elements: result.results
		} );
		this.$pinned.append( section.$element );
	} );
};

ext.bluespiceWikiFarm.ui.InstancesMenuPanel.prototype.makeOtherPanel = function () {
	this.$other = $( '<div>' );
	this.$overview.append( this.$other );
	const skeleton = this.getSkeleton( 'other', 5, true );
	this.$other.append( skeleton.$element );

	// ToDO: latest visited should be added here as default - currently empty
	skeleton.$element.remove();
	const section = new ext.bluespiceWikiFarm.ui.widget.SearchableInstanceSectionWidget( {
		sectionId: 'other',
		title: mw.message( 'wikifarm-instances-menu-section-other' ).text(),
		elements: []
	} );
	section.connect( this, { favoured: 'loadFavourites' } );
	this.$other.append( section.$element );
};

ext.bluespiceWikiFarm.ui.InstancesMenuPanel.prototype.getSkeleton = function ( id, count, hasSearch ) {
	return new ext.bluespiceWikiFarm.ui.widget.InstanceSkeletonSectionWidget( {
		sectionId: id,
		count: count,
		hasSearch: hasSearch
	} );
};

ext.bluespiceWikiFarm.ui.InstancesMenuPanel.prototype.addSpecialPageLink = function () {
	let url = mw.util.getUrl( 'Special:Wikis' );
	if ( this.farmConfig.instanceId !== 'w' ) {
		url = mw.config.get( 'wgServer' ) + '/wiki/Special:Wikis';
	}

	const specialPageLink = new OOJSPlus.ui.widget.LinkWidget( {
		href: url,
		label: mw.message( 'wikifarm-instances-menu-link-wikis-label' ).text()
	} );
	const $linkCnt = $( '<div>' ).addClass( 'd-flex justify-content-center' );
	$linkCnt.append( specialPageLink.$element );
	this.$element.append( $linkCnt );
};
