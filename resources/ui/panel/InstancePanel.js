bs.util.registerNamespace( 'bs.bluespiceWikiFarm.ui' );

bs.bluespiceWikiFarm.ui.InstancePanel = function ( cfg ) {
	cfg = cfg || {};
	cfg.expanded = false;
	cfg.padded = true;
	bs.bluespiceWikiFarm.ui.InstancePanel.parent.call( this, cfg );
	this.favourite = cfg.favourite || false;
	this.instances = cfg.instances || [];

	this.store = new OOJSPlus.ui.data.store.RemoteRestStore( {
		path: 'bluespice/farm/v1/instance/list',
		filter: {
			favourite: {
				type: 'boolean',
				value: this.favourite
			}
		}
	} );
	this.makeGrid();
	this.$element.addClass( 'wikifarm-instances-list' );
};

OO.inheritClass( bs.bluespiceWikiFarm.ui.InstancePanel, OO.ui.PanelLayout );

bs.bluespiceWikiFarm.ui.InstancePanel.prototype.makeGrid = function ( values ) {
	const gridCfg = {
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
						$color.css( 'background-color', row.instance_color.background );
					}
					return new OO.ui.HtmlSnippet( $( '<div>' ).append( $color, $anchor ).html() );
				}
			},
			/* eslint-disable-next-line camelcase */
			meta_group: {
				headerText: mw.msg( 'wikifarm-instances-grid-column-group-header-label' ),
				type: 'text',
				filter: { type: 'text' },
				sortable: true,
				hidden: true
			},
			favourite: {
				headerText: mw.msg( 'wikifarm-instances-grid-column-favourites-header-label' ),
				width: 50,
				valueParser: function ( value, row ) {
					if ( row.path === 'w' ) {
						return;
					}
					let iconName = 'star';
					let action = 'add';
					if ( value === true ) {
						iconName = 'unStar';
						action = 'remove';
					}
					const button = new OO.ui.ButtonWidget( {
						icon: iconName,
						framed: false,
						invisibleLabel: true,
						// The following messages are used here:
						// * wikifarm-instances-grid-favourites-label-add
						// * wikifarm-instances-grid-favourites-label-remove
						label: mw.message( 'wikifarm-instances-grid-favourites-label-' + action ).text()
					} );
					button.connect( this, {
						click: () => {
							const options = mw.user.options.get( 'bs-farm-instances-favorite' );
							const val = row.path;
							let newVal = '';
							let favouriteAction = 'add';
							if ( row.favourite ) {
								newVal = options.replace( val + ',', '' );
								favouriteAction = 'remove';
							} else {
								newVal = options + val + ',';
							}
							mw.loader.using( [ 'mediawiki.api' ] ).done( () => {
								mw.user.options.set( 'bs-farm-instances-favorite', newVal );
								new mw.Api().saveOption( 'bs-farm-instances-favorite', newVal ).done( () => {
									// The following messages are used here:
									// * wikifarm-instances-favourite-notification-add
									// * wikifarm-instances-favourite-notification-remove
									mw.notify(
										mw.message( 'wikifarm-instances-favourite-notification-' + favouriteAction, val ).text(),
										{ type: 'success' }
									);
									this.grid.store.reload();
								} );
							} );
						}
					} );
					return button.$element;
				}
			}
		}
	};
	gridCfg.data = values;
	gridCfg.store = this.store;
	this.grid = new OOJSPlus.ui.data.GridWidget( gridCfg );
	this.$element.append( this.grid.$element );
};

bs.bluespiceWikiFarm.ui.InstancePanel.prototype.reload = function () {
	this.grid.store.reload();
};
