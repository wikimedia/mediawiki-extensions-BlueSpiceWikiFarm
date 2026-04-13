ext.bluespiceWikiFarm.ui.AccessManagementPanel = function ( cfg ) {
	cfg = cfg || {};
	this.wikiFarmIsRoot = cfg.wikiFarmIsRoot || false;
	this.accessLevel = cfg.accessLevel || 'private';
	this.alwaysVisible = cfg.alwaysVisible || false;

	ext.bluespiceWikiFarm.ui.AccessManagementPanel.parent.call( this, {
		expanded: false,
		framed: true
	} );

	this.makeTabs();
	this.setupBeforeUnload();
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.AccessManagementPanel, OO.ui.IndexLayout );

ext.bluespiceWikiFarm.ui.AccessManagementPanel.prototype.makeTabs = function () {
	this.generalSettingsTab = new OO.ui.TabPanelLayout( 'general', {
		label: mw.msg( 'wikifarm-access-tab-general-settings' ),
		expanded: false
	} );

	this.generalSettingsPanel = new ext.bluespiceWikiFarm.ui.GeneralSettingsPanel( {
		accessLevel: this.accessLevel,
		alwaysVisible: this.alwaysVisible
	} );
	this.generalSettingsTab.$element.append( this.generalSettingsPanel.$element );

	this.rolesTab = new OO.ui.TabPanelLayout( 'roles', {
		label: mw.msg( 'wikifarm-access-tab-roles' ),
		expanded: false
	} );

	this.rolesPanel = new ext.bluespiceWikiFarm.ui.RolesPanel( {
		wikiFarmIsRoot: this.wikiFarmIsRoot
	} );
	this.rolesTab.$element.append( this.rolesPanel.$element );

	this.addTabPanels( [ this.generalSettingsTab, this.rolesTab ] );

	// Warn about unsaved changes when switching tabs
	this.on( 'set', ( tabPanel ) => {
		if ( tabPanel.getName() !== 'general' && this.generalSettingsPanel.hasUnsavedChanges() ) {
			OO.ui.confirm( mw.msg( 'wikifarm-access-unsaved-changes-warning' ) )
				.then( ( confirmed ) => {
					if ( confirmed ) {
						this.generalSettingsPanel.resetChanges();
					} else {
						this.setTabPanel( 'general' );
					}
				} );
		}
	} );
};

ext.bluespiceWikiFarm.ui.AccessManagementPanel.prototype.setupBeforeUnload = function () {
	$( window ).on( 'beforeunload', () => {
		if ( this.generalSettingsPanel.hasUnsavedChanges() ) {
			return mw.msg( 'wikifarm-access-unsaved-changes-warning' );
		}
	} );
};
