ext.bluespiceWikiFarm.ui.dialog.AddAccessAssignmentDialog = function ( config ) {
	config = config || {};
	ext.bluespiceWikiFarm.ui.dialog.AddAccessAssignmentDialog.parent.call( this, config );
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.dialog.AddAccessAssignmentDialog, ext.bluespiceWikiFarm.ui.dialog.EditAccessAssignmentDialog );

ext.bluespiceWikiFarm.ui.dialog.AddAccessAssignmentDialog.static.name = 'addAccessAssignment';
ext.bluespiceWikiFarm.ui.dialog.AddAccessAssignmentDialog.static.title = mw.msg( 'wikifarm-ui-access-action-add-assignment' );

ext.bluespiceWikiFarm.ui.dialog.AddAccessAssignmentDialog.prototype.getEntityWidget = function () {
	this.assignee = new ext.bluespiceWikiFarm.ui.widget.WikiAccessEntityPicker( {
		$overlay: this.$overlay
	} );
	this.assignee.connect( this, {
		change: 'checkValidity',
		choose: 'checkValidity'
	} );
	return this.assignee;
};

ext.bluespiceWikiFarm.ui.dialog.AddAccessAssignmentDialog.prototype.getSaveData = function () {
	const selectedAssignee = this.assignee.getSelectedItem();
	if ( !selectedAssignee ) {
		return null;
	}
	return {
		entityType: selectedAssignee.entityType,
		entityKey: selectedAssignee.entityKey,
		roleName: this.rolePicker.getMenu().findSelectedItem().getData(),
		globalAssignment: this.allowGlobal && this.globalCheck.isSelected()
	};
};

ext.bluespiceWikiFarm.ui.dialog.AddAccessAssignmentDialog.prototype.checkValidity = function () {
	const validAssignee = this.assignee.getSelectedItem() !== null;
	this.actions.setAbilities( { submit: validAssignee && !!this.rolePicker.getMenu().findSelectedItem() } );
};
