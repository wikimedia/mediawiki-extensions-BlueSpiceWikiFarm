bs.util.registerNamespace( 'bs.bluespiceWikiFarm.ui' );

bs.bluespiceWikiFarm.ui.WikiPanel = function ( cfg ) {
	cfg = cfg || {};
	cfg.expanded = false;
	cfg.padded = false;
	bs.bluespiceWikiFarm.ui.WikiPanel.parent.call( this, cfg );
	this.tab = cfg.tab;
	this.bucketsInitialized = false;
	this.label = cfg.label || '';
	this.favourite = cfg.favourite || false;
	this.instances = cfg.instances || [];
	this.permissions = cfg.permissions || [];
	this.name = cfg.name || '';
	this.showFavourite = cfg.showFavourite || false;
	this.farmConfig = ext.bluespiceWikiFarm._config(); // eslint-disable-line no-underscore-dangle

	this.store = cfg.store;
	this.store.connect( this, {
		loaded: () => {
			const numberWikis = this.store.getTotal();
			const $badgeNumer = $( '<span>' ).addClass( 'wikifarm-tab-badge' ).text( numberWikis );
			this.tab.getTabItem().setLabel(
				new OO.ui.HtmlSnippet( $( '<span>' ).text( this.label ).append( $badgeNumer ) )
			);

			if ( !this.bucketsInitialized ) {
				this.addGroupFilter();
				this.bucketsInitialized = true;
			}
		}
	} );
	this.makeGrid();
	this.$element.addClass( 'wikifarm-instances-list' );
};

OO.inheritClass( bs.bluespiceWikiFarm.ui.WikiPanel, OO.ui.PanelLayout );

bs.bluespiceWikiFarm.ui.WikiPanel.prototype.makeGrid = function () {
	const farmConfig = this.farmConfig;
	const gridCfg = {
		// resizable: false,
		filtering: null,
		classes: [ 'bs-wikis-list' ],
		columns: {
			title: {
				headerText: mw.msg( 'wikifarm-instances-grid-column-wiki-header-label' ),
				type: 'url',
				sortable: true,
				urlExternal: true,
				autoClosePopup: true,
				urlProperty: 'fullurl',
				filter: {
					type: 'text'
				},
				valueParser: function ( value, row ) {
					const $anchor = $( '<a>' ).attr( {
						href: row.fullurl,
						class: 'external',
						target: '_blank'
					} ).text( value );
					const $color = $( '<span>' ).addClass( 'instance-color-indicator' );
					if ( row.instance_color ) {
						$color.css( 'background-color', row.instance_color );
					}
					return new OO.ui.HtmlSnippet( $( '<div>' ).append( $color, $anchor ).html() );
				}
			}
		}
	};
	if ( this.showFavourite ) {
		gridCfg.columns.favourite = {
			headerText: mw.msg( 'wikifarm-instances-grid-column-favourites-header-label' ),
			width: 50,
			valueParser: function ( value, row ) {
				if ( row.path === 'w' || row.path === farmConfig.sharedWikiPath ) {
					return;
				}
				let iconName = 'star';
				let action = 'add';
				if ( value === true ) {
					iconName = 'unStar';
					action = 'remove';
				}
				const button = new OOJSPlus.ui.widget.ButtonWidget( {
					icon: iconName,
					framed: false,
					invisibleLabel: true,
					// The following messages are used here:
					// * wikifarm-instances-grid-favourites-label-add
					// * wikifarm-instances-grid-favourites-label-remove
					label: mw.message( 'wikifarm-instances-grid-favourites-label-' + action ).text()
				} );
				button.connect( this, {
					click: async () => {
						ext.bluespiceWikiFarm.util.toggleFavoriteInstance( row.path, row.title ).then(
							( performedAction ) => {
								if ( performedAction === 'add' ) {
									button.setIcon( 'unStar' );
								} else if ( performedAction === 'remove' ) {
									button.setIcon( 'star' );
								}
								this.grid.store.reload();
							}
						);
					}
				} );
				return button.$element;
			}
		};
	}
	if ( this.permissions.indexOf( 'managewiki' ) > -1 ) {
		gridCfg.columns.actionEdit = {
			type: 'action',
			actionId: 'edit',
			icon: 'edit',
			title: mw.message( 'wikifarm-button-action-label-edit' ).text(),
			headerText: mw.message( 'wikifarm-button-action-label-edit' ).text(),
			invisibleHeader: true,
			width: 30,
			visibleOnHover: true,
			shouldShow: ( row ) => !row.is_system
		};
	}
	gridCfg.store = this.store;
	this.grid = new OOJSPlus.ui.data.GridWidget( gridCfg );

	this.grid.connect( this, {
		action: ( action, row ) => {
			if ( action !== 'edit' ) {
				return;
			}
			const url = mw.util.getUrl( 'Special:Farm_management/' + row.path, {
				backTo: mw.config.get( 'wgPageName' )
			} );
			window.location.href = url;
		}
	} );

	this.$element.append( this.grid.$element );
};

bs.bluespiceWikiFarm.ui.WikiPanel.prototype.reload = function () {
	this.grid.store.reload();
};

bs.bluespiceWikiFarm.ui.WikiPanel.prototype.search = function ( query ) {
	this.grid.store.query( query );
};

bs.bluespiceWikiFarm.ui.WikiPanel.prototype.addGroupFilter = function () {
	if ( this.filter ) {
		this.filter.$element.remove();
	}
	const buckets = this.store.getBuckets();
	if ( !buckets.hasOwnProperty( 'groups' ) || buckets.groups.length === 0 ) {
		return;
	}
	const groups = [];
	for ( const i in buckets.groups ) {
		groups.push( {
			data: buckets.groups[ i ],
			label: buckets.groups[ i ]
		} );
	}
	this.filter = new OOJSPlus.ui.widget.FilterBarWidget( {
		noFilterActiveLabel: mw.message( 'wikifarm-instances-filter-show-all-label' ).text(),
		filterElements: groups
	} );

	const filterFactory = new OOJSPlus.ui.data.FilterFactory();
	this.filter.connect( this, {
		select: ( filter ) => {
			this.grid.store.filter(
				filterFactory.makeFilter( {
					value: filter,
					type: 'string'
				} ),
				'meta_group'
			);
		},
		clear: () => {
			this.grid.store.filter(
				filterFactory.makeFilter( {
					value: '',
					type: 'string'
				} ),
				'meta_group'
			);
		}
	} );
	this.$element.prepend( this.filter.$element );
};
