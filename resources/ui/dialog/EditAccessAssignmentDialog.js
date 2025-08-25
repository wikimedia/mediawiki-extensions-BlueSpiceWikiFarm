ext.bluespiceWikiFarm.ui.dialog.EditAccessAssignmentDialog = function ( config ) {
	config = config || {};
	this.allowGlobal = config.allowGlobalAssignments;
	this.entity = config.entity || {};
	ext.bluespiceWikiFarm.ui.dialog.EditAccessAssignmentDialog.parent.call( this, config );
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.dialog.EditAccessAssignmentDialog, OO.ui.ProcessDialog );

ext.bluespiceWikiFarm.ui.dialog.EditAccessAssignmentDialog.static.name = 'editAccessAssignment';
ext.bluespiceWikiFarm.ui.dialog.EditAccessAssignmentDialog.static.title = mw.msg( 'wikifarm-ui-access-action-edit-assignment' );
ext.bluespiceWikiFarm.ui.dialog.EditAccessAssignmentDialog.static.actions = [
	{ action: 'close', label: mw.message( 'wikifarm-button-action-label-cancel' ).text(), flags: 'safe' },
	{
		action: 'submit',
		label: mw.message( 'wikifarm-button-action-label-add' ).text(),
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
	this.panel = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );
	this.actions.setAbilities( { submit: false } );
	this.addItems();
	this.$body.append( this.panel.$element );
};

ext.bluespiceWikiFarm.ui.dialog.EditAccessAssignmentDialog.prototype.addItems = function () {
	this.rolePicker = new OO.ui.DropdownWidget( {
		$overlay: this.$overlay,
		menu: {
			items: [
				new OO.ui.MenuOptionWidget( {
					data: 'reader',
					label: mw.message( 'wikifarm-ui-role-label-reader' ).text()
				} ),
				new OO.ui.MenuOptionWidget( {
					data: 'editor',
					label: mw.message( 'wikifarm-ui-role-label-editor' ).text()
				} ),
				new OO.ui.MenuOptionWidget( {
					data: 'reviewer',
					label: mw.message( 'wikifarm-ui-role-label-reviewer' ).text()
				} ),
				new OO.ui.MenuOptionWidget( {
					data: 'maintainer',
					label: mw.message( 'wikifarm-ui-role-label-maintainer' ).text()
				} )
			]
		}
	} );
	this.rolePicker.getMenu().connect( this, {
		select: 'checkValidity'
	} );
	this.rolePicker.menu.selectItemByData( this.entity ? this.entity.role : 'reader' );
	this.globalCheck = new OO.ui.CheckboxInputWidget( {
		selected: this.entity ? this.entity.is_global_assignment : false
	} );
	this.panel.$element.append(
		new OO.ui.FieldLayout( this.getEntityWidget(), {
			label: mw.message( 'wikifarm-ui-access-field-key' ).text(),
			align: 'left'
		} ).$element,
		new OO.ui.FieldLayout( this.rolePicker, {
			label: mw.message( 'wikifarm-ui-access-field-role' ).text(),
			align: 'left'
		} ).$element
	);
	if ( this.allowGlobal ) {
		this.panel.$element.append( new OO.ui.FieldLayout( this.globalCheck, {
			label: mw.message( 'wikifarm-ui-access-field-global' ).text(),
			align: 'left'
		} ).$element );
	}
};

ext.bluespiceWikiFarm.ui.dialog.EditAccessAssignmentDialog.prototype.getEntityWidget = function () {
	if ( this.entity.entity_type === 'user' ) {
		return new OOJSPlus.ui.widget.UserWidget( {
			user_name: this.entity.entity_key // eslint-disable-line camelcase
		} );
	}
	return new ext.bluespiceWikiFarm.ui.widget.WikiAccessEntity( this.entity );
};

ext.bluespiceWikiFarm.ui.dialog.EditAccessAssignmentDialog.prototype.getActionProcess = function ( action ) {
	if ( action === 'close' ) {
		return new OO.ui.Process( function () {
			this.close();
		}, this );
	}
	if ( action === 'submit' ) {
		return new OO.ui.Process( function () {
			const dfd = $.Deferred();
			this.actions.setAbilities( { submit: false, close: false } );
			this.pushPending();
			$.ajax( {
				url: mw.util.wikiScript( 'rest' ) + '/bluespice/farm/v1/access/assign',
				type: 'POST',
				data: JSON.stringify( this.getSaveData() ),
				dataType: 'json',
				contentType: 'application/json; charset=UTF-8'
			} ).done( ( data ) => { // eslint-disable-line no-unused-vars
				this.close( { action: 'submit' } );
				dfd.resolve();
			} ).fail( () => {
				this.popPending();
				dfd.reject();
			} );

			return dfd.promise();
		}, this );
	}
	return ext.bluespiceWikiFarm.ui.dialog.EditAccessAssignmentDialog.parent.prototype.getActionProcess.call( this, action );
};

ext.bluespiceWikiFarm.ui.dialog.EditAccessAssignmentDialog.prototype.getSaveData = function () {
	return {
		entityType: this.entity.entity_type,
		entityKey: this.entity.entity_key,
		roleName: this.rolePicker.getMenu().findSelectedItem().getData(),
		globalAssignment: this.allowGlobal && this.globalCheck.isSelected()
	};
};

ext.bluespiceWikiFarm.ui.dialog.EditAccessAssignmentDialog.prototype.checkValidity = function () {
	this.actions.setAbilities( { submit: !!this.rolePicker.getMenu().findSelectedItem() } );
};

ext.bluespiceWikiFarm.ui.dialog.EditAccessAssignmentDialog.prototype.getBodyHeight = function () {
	if ( !this.$errors.hasClass( 'oo-ui-element-hidden' ) ) {
		return this.$element.find( '.oo-ui-processDialog-errors' )[ 0 ].scrollHeight;
	}
	return this.$element.find( '.oo-ui-window-body' )[ 0 ].scrollHeight;
};

ext.bluespiceWikiFarm.ui.dialog.EditAccessAssignmentDialog.prototype.showErrors = function ( errors ) {
	ext.bluespiceWikiFarm.ui.dialog.EditAccessAssignmentDialog.parent.prototype.showErrors.call( this, errors );
	this.updateSize();
};
