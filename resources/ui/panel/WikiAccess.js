ext.bluespiceWikiFarm.ui.WikiAccessPanel = function ( cfg ) {
	cfg = cfg || {};

	this.wikiFarmIsRoot = cfg.wikiFarmIsRoot || false;
	this.accessLevel = cfg.accessLevel || 'private';
	this.alwaysVisible = cfg.alwaysVisible || false;

	const columns = {
		entity_type: { // eslint-disable-line camelcase
			type: 'text',
			headerText: mw.msg( 'wikifarm-ui-access-field-type' ),
			filter: {
				type: 'string'
			},
			sortable: true,
			hidden: true
		},
		entity_key: { // eslint-disable-line camelcase
			type: 'text',
			headerText: mw.msg( 'wikifarm-ui-access-field-key' ),
			filter: { type: 'text' },
			valueParser: function ( value, row ) {
				return new ext.bluespiceWikiFarm.ui.widget.WikiAccessEntity( row );
			}
		},
		role: {
			width: 150,
			type: 'text',
			headerText: mw.msg( 'wikifarm-ui-access-field-role' ),
			valueParser: function ( value ) {
				// * wikifarm-ui-role-label-reader
				// * wikifarm-ui-role-label-editor
				// * wikifarm-ui-role-label-reviewer
				// * wikifarm-ui-role-label-maintainer
				return mw.msg( 'wikifarm-ui-role-label-' + value );
			}
		},
		actionEdit: {
			type: 'action',
			title: mw.msg( 'wikifarm-ui-access-action-edit' ),
			actionId: 'edit',
			icon: 'edit',
			headerText: mw.msg( 'wikifarm-ui-access-action-edit' ),
			invisibleHeader: true,
			width: 30,
			disabled: ( row ) => !this.wikiFarmIsRoot && row.is_global_assignment
		},
		actionDelete: {
			type: 'action',
			title: mw.msg( 'wikifarm-ui-access-action-delete' ),
			actionId: 'delete',
			icon: 'trash',
			headerText: mw.msg( 'wikifarm-ui-access-action-delete' ),
			invisibleHeader: true,
			width: 30,
			disabled: ( row ) => !this.wikiFarmIsRoot && row.is_global_assignment

		}
	};
	this.store = new OOJSPlus.ui.data.store.RemoteRestStore( {
		path: 'bluespice/farm/v1/access/assignments',
		sorter: {
			is_global_assignment: { direction: 'desc' } // eslint-disable-line camelcase
		}
	} );
	cfg.grid = {
		store: this.store,
		columns: columns,
		multiSelect: false
	};
	ext.bluespiceWikiFarm.ui.WikiAccessPanel.parent.call( this, cfg );

	this.$element.prepend(
		$( '<h3>' ).text( mw.msg( 'wikifarm-ui-access-assignments-header' ) )
	);
	this.addWikiTypeWidget();
	this.$element.removeClass( 'oo-ui-panelLayout-padded' );
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.WikiAccessPanel, OOJSPlus.ui.panel.ManagerGrid );

ext.bluespiceWikiFarm.ui.WikiAccessPanel.prototype.getToolbarActions = function () {
	return [
		this.getAddAction( {
			flags: [ 'progressive' ], displayBothIconAndLabel: true,
			title: mw.msg( 'wikifarm-ui-access-action-create-new-rule' )
		} )
	];
};

ext.bluespiceWikiFarm.ui.WikiAccessPanel.prototype.onAction = function ( action, row ) {
	if ( action === 'add' ) {
		this.openDialog(
			new ext.bluespiceWikiFarm.ui.dialog.AddAccessAssignmentDialog( {
				allowGlobalAssignments: this.wikiFarmIsRoot
			} ),
			( data ) => {
				if ( data.action === 'submit' ) {
					this.store.reload();
				}
			}
		);
	}
	if ( action === 'edit' ) {
		if ( !row ) {
			return;
		}
		this.openDialog(
			new ext.bluespiceWikiFarm.ui.dialog.EditAccessAssignmentDialog( {
				allowGlobalAssignments: this.wikiFarmIsRoot,
				entity: row
			} ),
			( data ) => {
				if ( data.action === 'submit' ) {
					this.store.reload();
				}
			}
		);
	}
	if ( action === 'delete' ) {
		if ( !row ) {
			return;
		}
		OO.ui.confirm( mw.msg( 'wikifarm-ui-access-confirm-delete-assignment', row.name ) )
			.then( ( confirmed ) => {
				if ( confirmed ) {
					$.ajax( {
						url: mw.util.wikiScript( 'rest' ) + '/bluespice/farm/v1/access/assign',
						type: 'POST',
						data: JSON.stringify( {
							entityType: row.entity_type,
							entityKey: row.entity_key,
							roleName: '',
							globalAssignment: row.is_global_assignment
						} ),
						dataType: 'json',
						contentType: 'application/json; charset=UTF-8'
					} ).then( () => {
						this.store.reload();
					}, () => {
						OO.ui.alert( mw.msg( 'wikifarm-ui-access-error-delete-assignment' ) );
					} );
				}
			} );
	}
};

ext.bluespiceWikiFarm.ui.WikiAccessPanel.prototype.openDialog = function ( dialog, callback ) {
	const wm = OO.ui.getWindowManager();
	wm.addWindows( [ dialog ] );
	wm.openWindow( dialog ).closed.then( callback );
};

ext.bluespiceWikiFarm.ui.WikiAccessPanel.prototype.getInitialAbilities = function () {
	return {
		add: true
	};
};

ext.bluespiceWikiFarm.ui.WikiAccessPanel.prototype.addWikiTypeWidget = function () {
	this.wikiType = new ext.bluespiceWikiFarm.ui.widget.AccessLevelInput( {
		enabledItems: this.alwaysVisible ? [ 'public', 'protected' ] : [ 'public', 'private', 'protected' ]
	} );
	this.wikiType.selectItemByData( this.accessLevel );
	this.wikiType.connect( this, {
		select: function ( item ) {
			this.setAccessLevel( item.getData() );
		}
	} );
	this.$element.prepend(
		$( '<h3>' ).text( mw.msg( 'wikifarm-ui-access-wiki-type-label' ) ),
		this.wikiType.$element
	);
};

ext.bluespiceWikiFarm.ui.WikiAccessPanel.prototype.setAccessLevel = function ( level ) {
	const data = { WikiFarmAccessLevel: level };
	bs.api.tasks.exec( 'configmanager', 'save', data )
		.done( ( response ) => {
			if ( !response.hasOwnProperty( 'success' ) || !response.success ) {
				OO.ui.alert( mw.msg( 'wikifarm-ui-access-error-set-level' ) );
				return;
			}
			this.accessLevel = level;
			mw.notify( mw.msg( 'wikifarm-ui-access-success-set-level', level ) );
		} ).fail( () => {
			OO.ui.alert( mw.msg( 'wikifarm-ui-access-error-set-level' ) );
		} );
};
