ext.bluespiceWikiFarm.ui.dialog.AddRoleAssignmentDialog = function ( config ) {
	config = config || {};
	this.allowGlobal = config.allowGlobalAssignments || false;
	ext.bluespiceWikiFarm.ui.dialog.AddRoleAssignmentDialog.parent.call( this, config );
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.dialog.AddRoleAssignmentDialog, OO.ui.ProcessDialog );

ext.bluespiceWikiFarm.ui.dialog.AddRoleAssignmentDialog.static.name = 'addRoleAssignment';
ext.bluespiceWikiFarm.ui.dialog.AddRoleAssignmentDialog.static.title = mw.msg( 'wikifarm-access-add-role-assignment' );
ext.bluespiceWikiFarm.ui.dialog.AddRoleAssignmentDialog.static.size = 'medium';
ext.bluespiceWikiFarm.ui.dialog.AddRoleAssignmentDialog.static.actions = [
	{ action: 'close', label: mw.message( 'wikifarm-button-action-label-cancel' ).text(), flags: 'safe' },
	{
		action: 'submit',
		label: mw.message( 'wikifarm-button-action-label-add' ).text(),
		flags: [ 'progressive', 'primary' ]
	}
];

ext.bluespiceWikiFarm.ui.dialog.AddRoleAssignmentDialog.prototype.initialize = function () {
	ext.bluespiceWikiFarm.ui.dialog.AddRoleAssignmentDialog.parent.prototype.initialize.apply( this, arguments );
	this.panel = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );
	this.actions.setAbilities( { submit: false } );

	this.selectedEntities = [];

	// Entity picker (supports adding multiple users/groups)
	this.entityPicker = new ext.bluespiceWikiFarm.ui.widget.WikiAccessEntityPicker( {
		$overlay: this.$overlay
	} );
	this.entityPicker.connect( this, {
		choose: 'onEntityChosen'
	} );

	// Tags shown below picker to display selected entities
	this.selectedTags = new OO.ui.TagMultiselectWidget( {
		inputPosition: 'none',
		allowArbitrary: true
	} );
	this.selectedTags.connect( this, {
		change: 'checkValidity'
	} );
	this.selectedTags.toggle( false );

	// Role picker with descriptions
	this.rolePicker = new OO.ui.DropdownWidget( {
		$overlay: this.$overlay,
		menu: {
			items: [
				new OOJSPlus.ui.widget.MenuOptionWithDescription( {
					data: 'reader',
					label: mw.message( 'wikifarm-ui-role-label-reader' ).text(),
					description: mw.msg( 'wikifarm-access-role-desc-reader' )
				} ),
				new OOJSPlus.ui.widget.MenuOptionWithDescription( {
					data: 'editor',
					label: mw.message( 'wikifarm-ui-role-label-editor' ).text(),
					description: mw.msg( 'wikifarm-access-role-desc-editor' )
				} ),
				new OOJSPlus.ui.widget.MenuOptionWithDescription( {
					data: 'reviewer',
					label: mw.message( 'wikifarm-ui-role-label-reviewer' ).text(),
					description: mw.msg( 'wikifarm-access-role-desc-reviewer' )
				} ),
				new OOJSPlus.ui.widget.MenuOptionWithDescription( {
					data: 'admin',
					label: mw.message( 'wikifarm-ui-role-label-admin' ).text(),
					description: mw.msg( 'wikifarm-access-role-desc-admin' )
				} )
			]
		}
	} );
	this.rolePicker.getMenu().connect( this, { select: 'checkValidity' } );

	this.globalCheck = new OO.ui.CheckboxInputWidget( { selected: false } );

	this.panel.$element.append(
		new OO.ui.FieldLayout( this.entityPicker, {
			label: mw.message( 'wikifarm-ui-access-field-select-entities' ).text(),
			align: 'left'
		} ).$element,
		this.selectedTags.$element,
		new OO.ui.FieldLayout( this.rolePicker, {
			label: mw.message( 'wikifarm-ui-access-field-role' ).text(),
			align: 'left'
		} ).$element
	);

	if ( this.allowGlobal ) {
		this.panel.$element.append( new OO.ui.FieldLayout( this.globalCheck, {
			label: mw.message( 'wikifarm-ui-access-field-global' ).text(),
			align: 'inline'
		} ).$element );
	}

	this.$body.append( this.panel.$element );
};

ext.bluespiceWikiFarm.ui.dialog.AddRoleAssignmentDialog.prototype.getSetupProcess = function () {
	return ext.bluespiceWikiFarm.ui.dialog.AddRoleAssignmentDialog.parent.prototype.getSetupProcess.call( this ).next( function () {
		this.checkValidity();
	}, this );
};

ext.bluespiceWikiFarm.ui.dialog.AddRoleAssignmentDialog.prototype.onEntityChosen = function ( selectedItem ) {
	if ( !selectedItem ) {
		return;
	}
	// Check for duplicates
	const key = selectedItem.entityType + ':' + selectedItem.entityKey;
	const existing = this.selectedTags.findItemFromData( key );
	if ( existing ) {
		return;
	}

	const typeLabel = selectedItem.entityType === 'user' ?
		mw.msg( 'wikifarm-ui-access-assignee-type-user' ) :
		mw.msg( 'wikifarm-ui-access-assignee-type-group' );

	this.selectedTags.addTag( key, typeLabel + ': ' + selectedItem.entityKey );
	this.selectedTags.toggle( true );
	this.selectedEntities.push( selectedItem );

	// Clear the picker
	this.entityPicker.setValue( '' );
	this.entityPicker.selectedItem = null;
	this.checkValidity();
};

ext.bluespiceWikiFarm.ui.dialog.AddRoleAssignmentDialog.prototype.checkValidity = function () {
	const hasEntities = this.selectedTags.getItems().length > 0;
	this.selectedTags.toggle( hasEntities );
	const hasRole = !!this.rolePicker.getMenu().findSelectedItem();
	this.actions.setAbilities( { submit: hasEntities && hasRole } );
};

ext.bluespiceWikiFarm.ui.dialog.AddRoleAssignmentDialog.prototype.getActionProcess = function ( action ) {
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

			const roleName = this.rolePicker.getMenu().findSelectedItem().getData();
			const isGlobal = this.allowGlobal && this.globalCheck && this.globalCheck.isSelected();

			// Reconcile selected entities from tags
			const currentTagKeys = this.selectedTags.getItems().map( ( item ) => item.getData() );
			const entities = this.selectedEntities.filter( ( entity ) => {
				const key = entity.entityType + ':' + entity.entityKey;
				return currentTagKeys.indexOf( key ) !== -1;
			} );

			const promises = entities.map( ( entity ) => $.ajax( {
				url: mw.util.wikiScript( 'rest' ) + '/bluespice/farm/v1/access/assign',
				type: 'POST',
				data: JSON.stringify( {
					entityType: entity.entityType,
					entityKey: entity.entityKey,
					roleName: roleName,
					globalAssignment: isGlobal
				} ),
				dataType: 'json',
				contentType: 'application/json; charset=UTF-8'
			} ) );

			$.when.apply( $, promises ).done( () => {
				this.close( { action: 'submit' } );
				dfd.resolve();
			} ).fail( () => {
				this.popPending();
				this.actions.setAbilities( { submit: true, close: true } );
				dfd.reject( new OO.ui.Error( mw.msg( 'wikifarm-ui-access-error-delete-assignment' ) ) );
			} );

			return dfd.promise();
		}, this );
	}
	return ext.bluespiceWikiFarm.ui.dialog.AddRoleAssignmentDialog.parent.prototype.getActionProcess.call( this, action );
};

ext.bluespiceWikiFarm.ui.dialog.AddRoleAssignmentDialog.prototype.getBodyHeight = function () {
	if ( !this.$errors.hasClass( 'oo-ui-element-hidden' ) ) {
		return this.$element.find( '.oo-ui-processDialog-errors' )[ 0 ].scrollHeight;
	}
	return this.$element.find( '.oo-ui-window-body' )[ 0 ].scrollHeight + 20;
};
