bs.util.registerNamespace( 'bs.bluespiceWikiFarm.ui' );

require( './WikiPanel.js' );

bs.bluespiceWikiFarm.ui.WikisPanel = function ( cfg ) {
	cfg = cfg || {};
	bs.bluespiceWikiFarm.ui.WikisPanel.parent.call( this, {
		expanded: false,
		framed: false
	} );
	this.permissions = cfg.permissions || [];
	this.creationAllowed = cfg.creationAllowed || false;

	if ( !this.creationAllowed ) {
		this.addLimitBanner();
	}

	this.makeSearch();
	this.makeTabs();
	if ( location.hash ) {
		const tab = location.hash.slice( 1 );
		this.indexLayout.setTabPanel( tab );
	}
};

OO.inheritClass( bs.bluespiceWikiFarm.ui.WikisPanel, OO.ui.Widget );

bs.bluespiceWikiFarm.ui.WikisPanel.prototype.addLimitBanner = function () {
	this.$noticeCnt = $( '<div>' ).css( 'margin-bottom', '20px' );
	this.$noticeCnt.prepend( new OO.ui.MessageWidget( {
		type: 'warning',
		label: mw.message( 'wikifarm-error-instance-limit-reached' ).text()
	} ).$element );
	this.$element.append( this.$noticeCnt );
};

bs.bluespiceWikiFarm.ui.WikisPanel.prototype.makeSearch = function () {
	this.searchInput = new OO.ui.SearchInputWidget( {
		icon: 'search'
	} );
	this.searchInput.connect( this, {
		change: OO.ui.debounce( this.onSearchChange.bind( this ), 200 )
	} );
	this.$element.append( new OO.ui.FieldLayout( this.searchInput, {
		label: mw.msg( 'wikifarm-instances-panel-search-label' ),
		align: 'top'
	} ).$element );
};

bs.bluespiceWikiFarm.ui.WikisPanel.prototype.makeTabs = function () {
	this.indexLayout = new OO.ui.IndexLayout( {
		expanded: false,
		framed: false,
		classes: [ 'wikifarm-wikis-index-layout' ]
	} );
	this.favouriteContent = new OO.ui.TabPanelLayout( 'favourite', {
		label: mw.msg( 'wikifarm-instances-tab-favourite-label' ),
		expanded: false,
		padded: false
	} );

	this.favouritePanel = new bs.bluespiceWikiFarm.ui.WikiPanel( {
		name: 'favourite',
		permissions: this.permissions,
		creationAllowed: this.creationAllowed,
		favourite: true,
		showFavourite: true,
		store: new OOJSPlus.ui.data.store.RemoteRestStore( {
			path: 'bluespice/farm/v1/instances/list',
			sorter: {
				title: {
					direction: 'ASC'
				}
			},
			filter: {
				favourite: {
					type: 'boolean',
					value: true
				}
			},
			noCache: true
		} )
	} );
	this.favouriteContent.$element.append( this.favouritePanel.$element );

	this.featuredContent = new OO.ui.TabPanelLayout( 'featured', {
		label: mw.msg( 'wikifarm-instances-tab-featured-label' ),
		expanded: false,
		padded: false
	} );
	this.featuredPanel = new bs.bluespiceWikiFarm.ui.WikiPanel( {
		name: 'featured',
		permissions: this.permissions,
		creationAllowed: this.creationAllowed,
		favourite: false,
		store: new OOJSPlus.ui.data.store.RemoteRestStore( {
			path: 'bluespice/farm/v1/instances/list',
			sorter: {
				title: {
					direction: 'ASC'
				}
			},
			filter: {
				pinned: {
					type: 'boolean',
					value: true
				}
			}
		} )
	} );
	this.featuredContent.$element.append( this.featuredPanel.$element );

	this.allInstanceContent = new OO.ui.TabPanelLayout( 'all', {
		label: mw.msg( 'wikifarm-instances-tab-all-label' ),
		expanded: false,
		padded: false
	} );
	this.allInstancePanel = new bs.bluespiceWikiFarm.ui.WikiPanel( {
		name: 'all',
		permissions: this.permissions,
		creationAllowed: this.creationAllowed,
		favourite: false,
		showFavourite: true,
		store: new OOJSPlus.ui.data.store.RemoteRestStore( {
			path: 'bluespice/farm/v1/instances/list',
			sorter: {
				title: {
					direction: 'ASC'
				}
			},
			filter: {
				favourite: {
					type: 'boolean',
					value: false
				}
			}
		} )
	} );
	this.allInstanceContent.$element.append( this.allInstancePanel.$element );
	this.indexLayout.addTabPanels( [ this.favouriteContent, this.featuredContent, this.allInstanceContent ] );

	this.indexLayout.connect( this, {
		set: ( page ) => {
			const pageName = page.getName();
			location.hash = pageName;
			if ( pageName === 'all' ) {
				this.allInstancePanel.reload();
			} else if ( pageName === 'featured' ) {
				this.featuredPanel.reload();
			} else {
				this.favouritePanel.reload();
			}
		}
	} );
	this.$element.append( this.indexLayout.$element );
};

bs.bluespiceWikiFarm.ui.WikisPanel.prototype.onSearchChange = function ( value ) {
	const query = value.trim();
	this.favouritePanel.search( query );
	this.featuredPanel.search( query );
	this.allInstancePanel.search( query );
};
