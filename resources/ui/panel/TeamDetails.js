ext.bluespiceWikiFarm.ui.TeamDetails = function ( cfg ) {
	cfg = cfg || {};
	cfg.padded = false;
	this.teamData = cfg.teamData;

	const columns = {
		name: {
			type: 'text',
			headerText: mw.msg( 'wikifarm-ui-teams-header-user' ),
			filter: {
				type: 'user'
			},
			valueParser: function ( value ) {
				return new OOJSPlus.ui.widget.UserWidget( {
					user_name: value // eslint-disable-line camelcase
				} );
			},
			sortable: true
		},
		expiration: {
			type: 'text',
			headerText: mw.msg( 'wikifarm-ui-teams-header-expiration' ),
			filter: { type: 'text' },
			valueParser: function ( value, row ) {
				return mw.msg( 'wikifarm-ui-teams-header-expiration-value', row.expiration_formatted );
			},
			sortable: true,
			width: 230
		},
		actionDelete: {
			type: 'action',
			title: mw.message( 'wikifarm-ui-teams-action-remove-member' ).text(),
			actionId: 'deleteMember',
			icon: 'trash',
			headerText: mw.message( 'wikifarm-ui-teams-action-remove-member' ).text(),
			invisibleHeader: true,
			width: 30
		}
	};
	this.store = new OOJSPlus.ui.data.store.RemoteRestStore( {
		path: 'bluespice/farm/v1/teams/' + this.teamData.name + '/members'
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
				{ data: 'name', label: mw.msg( 'wikifarm-ui-teams-sort-user-name' ) },
				{ data: 'expiration', label: mw.msg( 'wikifarm-ui-teams-sort-expiration' ) }
			],
			direction: 'asc'
		}
	} );

	ext.bluespiceWikiFarm.ui.TeamDetails.parent.call( this, cfg );

	const $name = $( '<h2>' ).text( this.teamData.name );
	const $description = $( '<p>' ).text( this.teamData.description );
	const $memberLabel = $( '<h4>' ).text( mw.msg( 'wikifarm-ui-teams-section-members' ) );
	$name.insertAfter( this.toolbar.$element );
	$description.insertAfter( $name );
	$memberLabel.insertAfter( $description );
	this.externalFilter.$element.insertBefore( this.grid.$element );
	this.$element.removeClass( 'oo-ui-panelLayout-padded' );
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.TeamDetails, OOJSPlus.ui.panel.ManagerGrid );

ext.bluespiceWikiFarm.ui.TeamDetails.prototype.getToolbarActions = function () {
	return [
		this.getAddAction( {
			flags: [ 'progressive' ],
			title: mw.msg( 'wikifarm-ui-teams-action-add-member' ),
			displayBothIconAndLabel: true
		} ),
		this.getDeleteAction( {
			title: mw.msg( 'wikifarm-ui-teams-action-delete-team' ),
			displayBothIconAndLabel: true
		} )
	];
};

ext.bluespiceWikiFarm.ui.TeamDetails.prototype.getInitialAbilities = function () {
	return {
		add: true,
		delete: true
	};
};

ext.bluespiceWikiFarm.ui.TeamDetails.prototype.onAction = function ( action, row ) {
	if ( action === 'add' ) {
		this.openDialog(
			new ext.bluespiceWikiFarm.ui.dialog.AddTeamMemberDialog( { team: this.teamData.name } ),
			( data ) => {
				if ( data.action === 'submit' ) {
					this.store.reload();
				}
			}
		);
	}
	if ( action === 'delete' ) {
		OO.ui.confirm( mw.msg( 'wikifarm-ui-teams-confirm-delete-team', this.teamData.name ) )
			.then( ( confirmed ) => {
				if ( confirmed ) {
					$.ajax( {
						url: mw.util.wikiScript( 'rest' ) +
							'/bluespice/farm/v1/teams/' + encodeURIComponent( this.teamData.name ),
						type: 'DELETE',
						dataType: 'json',
						contentType: 'application/json; charset=UTF-8'
					} ).then( () => {
						window.location.href = mw.Title.makeTitle( -1, 'WikiTeams' ).getUrl();
					}, () => {
						OO.ui.alert( mw.msg( 'wikifarm-ui-teams-error-delete-team' ) );
					} );
				}
			} );

	}
	if ( action === 'deleteMember' ) {
		OO.ui.confirm( mw.msg( 'wikifarm-ui-teams-confirm-delete-member', row.name ) )
			.then( ( confirmed ) => {
				if ( confirmed ) {
					$.ajax( {
						url: mw.util.wikiScript( 'rest' ) +
							'/bluespice/farm/v1/teams/' + encodeURIComponent( this.teamData.name ) + '/unassign',
						type: 'POST',
						data: JSON.stringify( {
							user: row.name
						} ),
						dataType: 'json',
						contentType: 'application/json; charset=UTF-8'
					} ).then( () => {
						this.store.reload();
					}, () => {
						OO.ui.alert( mw.msg( 'wikifarm-ui-teams-error-delete-member' ) );
					} );
				}
			} );
	}
};

ext.bluespiceWikiFarm.ui.TeamDetails.prototype.openDialog = function ( dialog, callback ) {
	const wm = OO.ui.getWindowManager();
	wm.addWindows( [ dialog ] );
	wm.openWindow( dialog ).closed.then( callback );
};
