ext.bluespiceWikiFarm.ui.dialog.EditAccessAssignmentDialog = function ( config ) {
	config = config || {};
	this.allowGlobal = config.allowGlobalAssignments;
	this.entity = config.entity || {};
	ext.bluespiceWikiFarm.ui.dialog.EditAccessAssignmentDialog.parent.call( this, config );
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.dialog.EditAccessAssignmentDialog, ext.bluespiceWikiFarm.ui.dialog.AddRoleAssignmentDialog );

ext.bluespiceWikiFarm.ui.dialog.EditAccessAssignmentDialog.static.name = 'editAccessAssignment';
ext.bluespiceWikiFarm.ui.dialog.EditAccessAssignmentDialog.static.title = mw.msg( 'wikifarm-ui-access-action-edit-assignment' );
ext.bluespiceWikiFarm.ui.dialog.EditAccessAssignmentDialog.static.actions = [
	{ action: 'close', icon: 'close', flags: 'safe' },
	{
		action: 'submit',
		label: mw.message( 'wikifarm-button-action-label-save' ).text(),
		flags: [ 'progressive', 'primary' ]
	}
];

ext.bluespiceWikiFarm.ui.dialog.EditAccessAssignmentDialog.prototype.getSetupProcess = function () {
	return ext.bluespiceWikiFarm.ui.dialog.EditAccessAssignmentDialog.parent.prototype.getSetupProcess.call( this ).next( function () {
		this.checkValidity();
	}, this );
};

ext.bluespiceWikiFarm.ui.dialog.EditAccessAssignmentDialog.prototype.initialize = function () {
	ext.bluespiceWikiFarm.ui.dialog.EditAccessAssignmentDialog.parent.prototype.initialize.apply( this, arguments );

	if ( this.entity ) {
		this.userGroupPicker.setValue( {
			type: this.entity.entity_type,
			key: this.entity.entity_key
		} );
		// Once value is set, disable
		this.userGroupPicker.connect( this, {
			change: () => {
				this.userGroupPicker.setDisabled( true );
			}
		} );

		this.rolePicker.menu.selectItemByData( this.entity.role );
		this.globalCheck.setSelected( this.entity.is_global_assignment );
	}
};
