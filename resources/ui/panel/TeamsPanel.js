ext.bluespiceWikiFarm.ui.TeamsPanel = function ( cfg ) {
	cfg = cfg || {};
	cfg.padded = false;

	const columns = {
		name: {
			type: 'text',
			headerText: mw.msg( 'wikifarm-ui-teams-header-name' ),
			filter: {
				type: 'string'
			},
			sortable: true,
			valueParser: function ( value, row ) {
				const url = mw.Title.makeTitle( -1, 'WikiTeams/' + value ).getUrl( {
					backTo: mw.config.get( 'wgPageName' )
				} );
				const $anchor = '<a href="' + url + '">' + value + '</a>';
				const $desc = $( '<span>' ).addClass( 'bs-wiki-team-description' ).text( row.description );
				return new OO.ui.HtmlSnippet( $( '<div>' ).append( $anchor, $desc ).html() );
			}
		},
		memberCount: {
			width: 180,
			type: 'text',
			valueParser: function ( value ) {
				if ( value === 0 ) {
					return mw.msg( 'wikifarm-ui-teams-header-membercount-none' );
				}
				return mw.msg( 'wikifarm-ui-teams-header-membercount', value );
			}
		},
		actionDetails: {
			type: 'action',
			title: mw.msg( 'wikifarm-ui-teams-action-details' ),
			actionId: 'details',
			icon: 'info',
			headerText: mw.msg( 'wikifarm-ui-teams-action-details' ),
			invisibleHeader: true,
			width: 30
		}
	};
	this.store = new OOJSPlus.ui.data.store.RemoteRestStore( {
		path: 'bluespice/farm/v1/teams',
		sorter: {
			name: { direction: 'asc' }
		}
	} );
	cfg.grid = {
		store: this.store,
		columns: columns,
		multiSelect: false,
		noHeader: true
	};

	this.externalFilter = new OOJSPlus.ui.data.grid.ExternalFilter( {
		store: this.store,
		sort: {
			value: 'name',
			sortOptions: [
				{ data: 'name', label: mw.msg( 'wikifarm-ui-teams-sort-name' ) },
				{ data: 'memberCount', label: mw.msg( 'wikifarm-ui-teams-sort-membercount' ) }
			],
			direction: 'asc'
		}
	} );
	ext.bluespiceWikiFarm.ui.TeamsPanel.parent.call( this, cfg );

	this.externalFilter.$element.insertBefore( this.grid.$element );
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.TeamsPanel, OOJSPlus.ui.panel.ManagerGrid );

ext.bluespiceWikiFarm.ui.TeamsPanel.prototype.getToolbarActions = function () {
	return [
		this.getAddAction( {
			flags: [ 'progressive' ], displayBothIconAndLabel: true,
			title: mw.msg( 'wikifarm-ui-teams-action-create-team' )
		} )
	];
};

ext.bluespiceWikiFarm.ui.TeamsPanel.prototype.onAction = function ( action, row ) {
	if ( action === 'add' ) {
		this.openDialog(
			new ext.bluespiceWikiFarm.ui.dialog.CreateTeamDialog(),
			( data ) => {
				if ( data.action === 'submit' ) {
					window.location.href = mw.Title.makeTitle( -1, 'WikiTeams/' + data.name ).getUrl( {
						created: true,
						backTo: mw.config.get( 'wgPageName' )
					} );
				}
			}
		);
	}
	if ( action === 'details' ) {
		if ( !row ) {
			return;
		}
		window.location.href = mw.Title.makeTitle( -1, 'WikiTeams/' + row.name ).getUrl( {
			backTo: mw.config.get( 'wgPageName' )
		} );
	}
};

ext.bluespiceWikiFarm.ui.TeamsPanel.prototype.openDialog = function ( dialog, callback ) {
	const wm = OO.ui.getWindowManager();
	wm.addWindows( [ dialog ] );
	wm.openWindow( dialog ).closed.then( callback );
};

ext.bluespiceWikiFarm.ui.TeamsPanel.prototype.getInitialAbilities = function () {
	return {
		add: true
	};
};
