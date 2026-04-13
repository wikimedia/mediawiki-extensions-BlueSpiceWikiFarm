ext.bluespiceWikiFarm.ui.RolesPanel = function ( cfg ) {
	cfg = cfg || {};
	this.wikiFarmIsRoot = cfg.wikiFarmIsRoot || false;

	ext.bluespiceWikiFarm.ui.RolesPanel.parent.call( this, {
		padded: true,
		expanded: false
	} );

	this.$element.addClass( 'bs-access-management-roles' );
	this.build();
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.RolesPanel, OO.ui.PanelLayout );

ext.bluespiceWikiFarm.ui.RolesPanel.prototype.build = function () {
	// Add role assignment button in toolbar
	this.addButton = new OO.ui.ButtonWidget( {
		label: mw.msg( 'wikifarm-access-add-role-assignment' ),
		icon: 'add',
		flags: [ 'progressive' ]
	} );
	this.addButton.connect( this, { click: 'onAddRoleAssignment' } );

	this.$toolbar = $( '<div>' ).addClass( 'bs-access-roles-toolbar' );
	this.$toolbar.append( this.addButton.$element );

	// Create stores for both tabs
	this.groupStore = new OOJSPlus.ui.data.store.RemoteRestStore( {
		path: 'bluespice/farm/v1/access/assignments',
		filter: {
			entity_type: { // eslint-disable-line camelcase
				type: 'string',
				operator: 'eq',
				value: 'group'
			}
		},
		sorter: {
			is_global_assignment: { direction: 'desc' } // eslint-disable-line camelcase
		}
	} );

	this.userStore = new OOJSPlus.ui.data.store.RemoteRestStore( {
		path: 'bluespice/farm/v1/access/assignments',
		filter: {
			entity_type: { // eslint-disable-line camelcase
				type: 'string',
				operator: 'eq',
				value: 'user'
			}
		},
		sorter: {
			is_global_assignment: { direction: 'desc' } // eslint-disable-line camelcase
		}
	} );

	// Create tabs
	this.tabLayout = new OO.ui.IndexLayout( {
		expanded: false,
		framed: true
	} );

	this.groupsTab = new OO.ui.TabPanelLayout( 'groups', {
		label: mw.msg( 'wikifarm-access-tab-groups' ),
		expanded: false
	} );

	this.usersTab = new OO.ui.TabPanelLayout( 'users', {
		label: mw.msg( 'wikifarm-access-tab-users' ),
		expanded: false
	} );

	// Build group grid
	this.groupGrid = this.buildGrid( this.groupStore, 'group' );
	this.groupsTab.$element.append( this.groupGrid.$element );

	// Build user grid
	this.userGrid = this.buildGrid( this.userStore, 'user' );
	this.usersTab.$element.append( this.userGrid.$element );

	this.tabLayout.addTabPanels( [ this.groupsTab, this.usersTab ] );

	// Load counts for badges
	this.loadCounts();

	this.$element.append( this.$toolbar, this.tabLayout.$element );
};

ext.bluespiceWikiFarm.ui.RolesPanel.prototype.buildGrid = function ( store, entityType ) {
	const columns = {
		entity_key: { // eslint-disable-line camelcase
			type: 'text',
			headerText: entityType === 'group' ?
				mw.msg( 'wikifarm-access-column-group' ) :
				mw.msg( 'wikifarm-access-column-user' ),
			valueParser: function ( value, row ) {
				if ( row.entity_type === 'user' ) {
					return new OOJSPlus.ui.widget.UserWidget( {
						user_name: row.entity_key // eslint-disable-line camelcase
					} ).$element;
				}
				const icon = new OO.ui.IconWidget( { icon: 'userGroup' } );
				const $label = $( '<span>' ).text( row.entity_key );
				const $container = $( '<span>' ).addClass( 'bs-access-group-entity' );
				$container.append( icon.$element, $label );
				if ( row.is_global_assignment ) {
					$container.append(
						$( '<span>' ).addClass( 'bs-access-global-badge' )
							.text( mw.msg( 'wikifarm-ui-access-label-global' ) )
					);
				}
				return $container;
			}
		},
		role: {
			width: 150,
			type: 'text',
			headerText: mw.msg( 'wikifarm-ui-access-field-role' ),
			filter: {
				type: 'list',
				list: [
					{ data: 'reader', label: mw.msg( 'wikifarm-ui-role-label-reader' ) },
					{ data: 'editor', label: mw.msg( 'wikifarm-ui-role-label-editor' ) },
					{ data: 'reviewer', label: mw.msg( 'wikifarm-ui-role-label-reviewer' ) },
					{ data: 'admin', label: mw.msg( 'wikifarm-ui-role-label-admin' ) }
				]
			},
			valueParser: function ( value ) {
				// * wikifarm-ui-role-label-reader
				// * wikifarm-ui-role-label-editor
				// * wikifarm-ui-role-label-reviewer
				// * wikifarm-ui-role-label-admin
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

	const grid = new OOJSPlus.ui.data.GridWidget( {
		store: store,
		columns: columns,
		multiSelect: false,
		filtering: { showQueryField: true }
	} );

	grid.connect( this, {
		action: function ( action, row ) {
			this.onGridAction( action, row, store );
		}
	} );

	return grid;
};

ext.bluespiceWikiFarm.ui.RolesPanel.prototype.onGridAction = function ( action, row, store ) { // eslint-disable-line no-unused-vars
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
					this.reloadAll();
				}
			}
		);
	}
	if ( action === 'delete' ) {
		if ( !row ) {
			return;
		}
		OO.ui.confirm( mw.msg( 'wikifarm-ui-access-confirm-delete-assignment' ) )
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
						this.reloadAll();
					}, () => {
						OO.ui.alert( mw.msg( 'wikifarm-ui-access-error-delete-assignment' ) );
					} );
				}
			} );
	}
};

ext.bluespiceWikiFarm.ui.RolesPanel.prototype.onAddRoleAssignment = function () {
	this.openDialog(
		new ext.bluespiceWikiFarm.ui.dialog.AddRoleAssignmentDialog( {
			allowGlobalAssignments: this.wikiFarmIsRoot
		} ),
		( data ) => {
			if ( data.action === 'submit' ) {
				this.reloadAll();
			}
		}
	);
};

ext.bluespiceWikiFarm.ui.RolesPanel.prototype.openDialog = function ( dialog, callback ) {
	const wm = OO.ui.getWindowManager();
	wm.addWindows( [ dialog ] );
	wm.openWindow( dialog ).closed.then( callback );
};

ext.bluespiceWikiFarm.ui.RolesPanel.prototype.reloadAll = function () {
	this.groupStore.reload();
	this.userStore.reload();
	this.loadCounts();
};

ext.bluespiceWikiFarm.ui.RolesPanel.prototype.loadCounts = function () {
	// Load all assignments to get counts
	$.ajax( {
		url: mw.util.wikiScript( 'rest' ) + '/bluespice/farm/v1/access/assignments',
		type: 'GET',
		dataType: 'json'
	} ).done( ( response ) => {
		const results = response.results || [];
		let groupCount = 0;
		let userCount = 0;
		for ( const item of results ) {
			if ( item.entity_type === 'group' ) {
				groupCount++;
			} else if ( item.entity_type === 'user' ) {
				userCount++;
			}
		}
		this.groupsTab.tabItem.setLabel(
			mw.msg( 'wikifarm-access-tab-groups' ) + ' (' + groupCount + ')'
		);
		this.usersTab.tabItem.setLabel(
			mw.msg( 'wikifarm-access-tab-users' ) + ' (' + userCount + ')'
		);
	} );
};
