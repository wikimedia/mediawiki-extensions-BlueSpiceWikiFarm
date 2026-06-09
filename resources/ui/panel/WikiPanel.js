bs.util.registerNamespace( 'bs.bluespiceWikiFarm.ui' );

bs.bluespiceWikiFarm.ui.WikiPanel = function ( cfg ) {
	cfg = cfg || {};
	cfg.expanded = false;
	cfg.padded = false;
	bs.bluespiceWikiFarm.ui.WikiPanel.parent.call( this, cfg );
	this.favourite = cfg.favourite || false;
	this.instances = cfg.instances || [];
	this.permissions = cfg.permissions || [];
	this.name = cfg.name || '';
	this.showFavourite = cfg.showFavourite || false;
	this.creationAllowed = cfg.creationAllowed || false;
	this.farmConfig = ext.bluespiceWikiFarm._config(); // eslint-disable-line no-underscore-dangle

	this.store = cfg.store;
	this.store.connect( this, {
		loaded: ( values ) => {
			this.showValues( values );
		}
	} );
	this.makeGrid();
	this.$element.addClass( 'wikifarm-instances-list' );
};

OO.inheritClass( bs.bluespiceWikiFarm.ui.WikiPanel, OO.ui.PanelLayout );

bs.bluespiceWikiFarm.ui.WikiPanel.prototype.makeGrid = function () {
	const farmConfig = this.farmConfig;
	const gridCfg = {
		resizable: false,
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
	const subActions = [];
	if ( this.permissions.indexOf( 'managewiki' ) > -1 ) {
		subActions.push( {
			label: mw.message( 'wikifarm-button-action-label-edit' ).text(),
			data: 'edit',
			icon: 'edit'
		} );
		subActions.push( {
			label: mw.message( 'wikifarm-button-action-label-suspend' ).text(),
			data: 'suspend',
			icon: 'pause'
		} );
	}

	if ( this.permissions.indexOf( 'createwiki' ) > -1 || !this.creationAllowed ) {
		subActions.push( {
			label: mw.message( 'wikifarm-button-action-label-clone' ).text(),
			data: 'clone',
			icon: 'copy'
		} );
	}
	if ( this.permissions.indexOf( 'deletewiki' ) > -1 ) {
		subActions.push( {
			label: mw.message( 'wikifarm-button-action-delete-label' ).text(),
			data: 'delete',
			icon: 'trash',
			flags: [ 'destructive' ]
		} );
	}
	gridCfg.columns.others = {
		type: 'secondaryActions',
		visibleOnHover: true,
		actions: subActions,
		width: 30
	};
	gridCfg.store = this.store;
	this.grid = new OOJSPlus.ui.data.GridWidget( gridCfg );

	this.grid.connect( this, {
		action: ( action, row ) => {
			if ( action === 'edit' ) {
				const url = mw.util.getUrl( 'Special:Farm_management/' + row.path, {
					backTo: mw.config.get( 'wgPageName' )
				} );
				window.location.href = url;
				return;
			}
			if ( action === 'clone' ) {
				const url = mw.util.getUrl( 'Special:Farm_management/_create/template', {
					template: '_clone',
					source: row.path,
					backTo: mw.config.get( 'wgPageName' )
				} );
				window.location.href = url;
				return;
			}
			this.openActionDialog( action, row );
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

bs.bluespiceWikiFarm.ui.WikiPanel.prototype.showValues = function ( values ) {
	const sortedValues = this.sortValues( values );
	this.addGroupFilter( values );
	this.grid.setItems( Object.values( sortedValues ) ); // eslint-disable-line es-x/no-object-values
};

bs.bluespiceWikiFarm.ui.WikiPanel.prototype.addGroupFilter = function ( values ) {
	const uniqueGroups = [
		...new Set(
			Object.values( values ) // eslint-disable-line es-x/no-object-values
				.map( ( item ) => item.meta_group )
				.filter( Boolean )
		)
	].sort();
	if ( this.filter ) {
		this.filter.$element.remove();
	}
	if ( uniqueGroups.length === 0 ) {
		return;
	}
	this.filter = new OOJSPlus.ui.widget.FilterBarWidget( {
		noFilterActiveLabel: mw.message( 'wikifarm-instances-filter-show-all-label' ).text(),
		filterElements: uniqueGroups
	} );

	this.filter.connect( this, {
		select: ( filter ) => {
			const filteredItems = Object.values( values ) // eslint-disable-line es-x/no-object-values
				.filter( ( item ) => item.meta_group === filter );
			this.grid.setItems( Object.values( filteredItems ) ); // eslint-disable-line es-x/no-object-values
		},
		clear: () => {
			this.grid.setItems( Object.values( values ) ); // eslint-disable-line es-x/no-object-values
		}
	} );
	this.$element.prepend( this.filter.$element );
};

bs.bluespiceWikiFarm.ui.WikiPanel.prototype.sortValues = function ( values ) {
	const pinnedPaths = [ 'w', this.farmConfig.sharedWikiPath ].filter( Boolean );
	const items = Object.values( values ); // eslint-disable-line es-x/no-object-values
	const pinned = pinnedPaths
		.map( ( p ) => items.find( ( i ) => i.path === p ) )
		.filter( Boolean );
	const rest = items.filter( ( i ) => !pinnedPaths.includes( i.path ) );

	Object.keys( values ).forEach( ( k ) => delete values[ k ] );
	[ ...pinned, ...rest ].forEach( ( item, i ) => {
		values[ i ] = item;
	} );
	return values;
};

bs.bluespiceWikiFarm.ui.WikiPanel.prototype.openActionDialog = function ( action, item ) {
	let dialog;
	mw.loader.using( [ 'ext.bluespice.wikiFarm.management' ] ).done( () => {
		switch ( action ) {
			case 'delete':
				dialog = new ext.bluespiceWikiFarm.ui.dialog.RemoveInstanceDialog( { path: item.path } );
				break;
			case 'suspend':
				dialog = new ext.bluespiceWikiFarm.ui.dialog.ConfirmDialog( {
					path: item.path,
					action: 'suspend',
					title: mw.message( 'wikifarm-suspend-instance' ).plain(),
					prompt: mw.message( 'wikifarm-suspend-prompt' ).plain()
				} );
				break;
		}

		if ( dialog ) {
			const windowManager = new OO.ui.WindowManager();
			$( document.body ).append( windowManager.$element );
			windowManager.addWindows( [ dialog ] );
			windowManager.openWindow( dialog ).closed.then( ( res ) => {
				$( document.body ).remove( windowManager.$element );
				if ( res && res.needsReload ) {
					this.grid.getStore().reload();
					this.evaluateLimits();
				}
			} );
		}
	} );
};
