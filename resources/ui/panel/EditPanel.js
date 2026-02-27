ext.bluespiceWikiFarm.ui.EditPanel = function ( config ) {
	this.instanceData = config.instanceData || {};
	this.pathIsPredefined = config.pathIsPredefined || false;
	this.meta = this.instanceData.sfi_meta ? JSON.parse( this.instanceData.sfi_meta ) : {};
	this.config = this.instanceData.sfi_config ? JSON.parse( this.instanceData.sfi_config ) : {};

	ext.bluespiceWikiFarm.ui.EditPanel.parent.call( this, {
		expanded: false,
		padded: false
	} );
	this.toolbar = ext.bluespiceWikiFarm.ui.createEditorToolbar(
		this.getSubmitButtonLabel(), this.getAdditionalToolbarActions()
	);
	this.toolbar.connect( this, {
		close: 'onCancelClick',
		save: 'onSubmit',
		suspend: 'onSuspendClick',
		resume: 'onResumeClick',
		delete: 'onRemoveClick',
		clone: 'onCloneClick'
	} );
	this.$element.append( this.toolbar.$element );

	this.farmConfig = ext.bluespiceWikiFarm._config(); // eslint-disable-line no-underscore-dangle

	this.msgPanel = new OO.ui.PanelLayout( {
		expanded: false,
		padded: false
	} );

	this.inputValidity = {
		name: true
	};

	this.$element.append( this.msgPanel.$element );

	this.render();
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.EditPanel, OO.ui.PanelLayout );

ext.bluespiceWikiFarm.ui.EditPanel.prototype.getAdditionalToolbarActions = function () {
	return [
		{
			visible: !this.instanceData.is_system && this.instanceData.sfi_status !== 'suspended',
			data: 'suspend',
			icon: 'pause',
			label: mw.message( 'wikifarm-suspend-instance' ).plain(),
			flags: [ 'destructive' ]
		},
		{
			visible: this.instanceData.sfi_status === 'suspended',
			data: 'resume',
			icon: 'play',
			label: mw.message( 'wikifarm-resume-instance' ).plain(),
			flags: [ 'progressive' ]
		},
		{
			data: 'delete',
			icon: 'trash',
			label: mw.message( 'wikifarm-remove-instance' ).plain(),
			flags: [ 'primary', 'destructive' ]
		},
		{
			data: 'clone',
			icon: 'articleDisambiguation',
			label: mw.message( 'wikifarm-clone-instance' ).plain(),
			flags: [ 'progressive' ]
		}
	];
};

ext.bluespiceWikiFarm.ui.EditPanel.prototype.render = function () {
	this.$element.append( this.makeInputs() );

	if ( this.instanceData.sfi_status === 'suspended' ) {
		this.msgPanel.$element.append(
			new OO.ui.MessageWidget( {
				type: 'warning',
				label: mw.msg( 'wikifarm-instance-suspended-notice' )
			} ).$element
		);
	}
};

ext.bluespiceWikiFarm.ui.EditPanel.prototype.makeInputs = function () {
	this.nameInput = new OO.ui.TextInputWidget( {
		name: 'name', required: true, value: this.instanceData.sfi_display_name || ''
	} );
	this.nameInput.connect( this, { change: 'onNameChange' } );
	this.nameLayout = new OO.ui.FieldLayout( this.nameInput, {
		label: mw.message( 'wikifarm-instance-title' ).plain(),
		align: 'top'
	} );
	this.pathInput = new OO.ui.TextInputWidget( {
		value: this.instanceData.sfi_path || '', disabled: true
	} );

	this.descriptionInput = new OO.ui.MultilineTextInputWidget( {
		rows: 3,
		value: this.meta.desc || ''
	} );

	this.advancedOptions = new OOJSPlus.ui.widget.ExpandablePanel( {
		expanded: false,
		padded: false,
		label: mw.msg( 'wikifarm-instance-advanced-options' ),
		collapsed: true,
		classes: [ 'ext-bluespiceWikiFarm-extra-options-panel' ]
	} );
	this.advancedOptions.$area.append( this.makeAdvancedOptionsInputs() );

	return [
		this.nameLayout.$element,
		new OO.ui.FieldLayout( this.descriptionInput, {
			align: 'top',
			label: mw.message( 'wikifarm-instance-desc' ).plain()
		} ).$element,
		new OO.ui.FieldLayout( this.pathInput, {
			align: 'top',
			label: mw.message( 'wikifarm-instance-path' ).plain()
		} ).$element,
		this.advancedOptions.$element
	];
};

ext.bluespiceWikiFarm.ui.EditPanel.prototype.makeAdvancedOptionsInputs = function () {
	this.groupInput = new OOJSPlus.ui.widget.StoreDataInputWidget( {
		queryAction: 'wikifarm-group-store',
		labelField: 'text',
		useQueryParam: true,
		allowArbitrary: true,
		value: this.meta.group || ''
	} );
	this.keywordsInput = new OOJSPlus.ui.widget.StoreDataTagMultiselectWidget( {
		queryAction: 'wikifarm-keyword-store',
		labelField: 'text',
		allowArbitrary: true
	} );
	this.keywordsInput.setValue( this.meta.keywords || [] );

	const languages = mw.config.get( 'wgWikiFarmAvailableLanguages' ) || [];
	this.language = new OO.ui.DropdownInputWidget( {
		options: languages,
		value: this.config.wgLanguageCode || mw.config.get( 'wgContentLanguage' )
	} );

	this.searchable = new OO.ui.ToggleSwitchWidget();
	this.searchable.setValue(
		this.meta.hasOwnProperty( 'notsearchable' ) ? !this.meta.notsearchable : true
	);

	this.color = new ext.bluespiceWikiFarm.ui.widget.InstanceColorWidget();
	this.color.setValue( this.meta.instanceColor || '' );

	let items = [
		new OO.ui.FieldLayout( this.groupInput, {
			align: 'top',
			label: mw.message( 'wikifarm-instance-group' ).plain()
		} ).$element,
		new OO.ui.FieldLayout( this.keywordsInput, {
			align: 'top',
			label: mw.message( 'wikifarm-instance-keywords' ).plain()
		} ).$element,
		new OO.ui.FieldLayout( this.language, {
			align: 'top',
			label: mw.message( 'wikifarm-lang-code' ).text()
		} ).$element,
		new OO.ui.FieldLayout( this.color, {
			align: 'top',
			label: mw.message( 'wikifarm-instance-color' ).text()
		} ).$element
	];
	if ( !( this.farmConfig.useUnifiedSearch || false ) ) {
		items = [
			new OO.ui.FieldLayout( this.searchable, {
				align: 'left',
				label: mw.message( 'wikifarm-instance-notsearchable' ).plain()
			} ).$element, ...items
		];
	}
	return items;
};

ext.bluespiceWikiFarm.ui.EditPanel.prototype.onNameChange = function ( value ) { // eslint-disable-line no-unused-vars
	this.onBeforeNameSet();
	// Wait for the user to stop typing
	if ( this.nameChangeTimeout ) {
		clearTimeout( this.nameChangeTimeout );
	}
	this.nameChangeTimeout = setTimeout( () => {
		const newValue = this.nameInput.getValue().trim();
		if ( newValue === '' ) {
			this.onAfterNameSet( true, false );
			return;
		}
		this.nameLayout.setWarnings( [] );
		this.inputValidity.name = false;
		this.generatePath( newValue ).done( ( r ) => {
			this.onAfterNameSet( false, true, r );
			this.inputValidity.name = true;
			if ( r.nameExists ) {
				this.nameLayout.setWarnings( [ mw.msg( 'wikifarm-instance-name-exists' ) ] );
			}
		} ).fail( () => {
			this.onAfterNameSet( false, false, {} );
		} );
	}, 500 );
};

ext.bluespiceWikiFarm.ui.EditPanel.prototype.onSuspendClick = function () {
	this.openTaskDialog( 'suspend' );
};

ext.bluespiceWikiFarm.ui.EditPanel.prototype.onResumeClick = function () {
	this.openTaskDialog( 'resume' );
};

ext.bluespiceWikiFarm.ui.EditPanel.prototype.onRemoveClick = function () {
	this.openTaskDialog( 'remove' );
};

ext.bluespiceWikiFarm.ui.EditPanel.prototype.onCloneClick = function () {
	window.location.href = this.getManagementUrl(
		'_create/template',
		{ template: '_clone', source: this.instanceData.sfi_path }
	);
};

ext.bluespiceWikiFarm.ui.EditPanel.prototype.onBeforeNameSet = function () {
	// NOOP
};

ext.bluespiceWikiFarm.ui.EditPanel.prototype.onAfterNameSet = function ( isEmpty, isValid, response ) { // eslint-disable-line no-unused-vars
	// NOOP
};

ext.bluespiceWikiFarm.ui.EditPanel.prototype.onSubmitDone = function ( response, success ) {
	if ( success ) {
		window.location.href = mw.Title.makeTitle( -1, mw.config.get( 'wgCanonicalSpecialPageName' ) ).getUrl();
	} else {
		ext.bluespiceWikiFarm.ui.EditPanel.prototype.onSubmitDone.call( this, response, false );
	}
};

ext.bluespiceWikiFarm.ui.EditPanel.prototype.onCancelClick = function () {
	window.location.href = mw.Title.makeTitle( -1, mw.config.get( 'wgCanonicalSpecialPageName' ) ).getUrl();
};

ext.bluespiceWikiFarm.ui.EditPanel.prototype.getSubmitButtonLabel = function () {
	return mw.message( 'wikifarm-button-action-label-save' ).plain();
};

ext.bluespiceWikiFarm.ui.EditPanel.prototype.generateSubmitData = function () {
	const dfd = $.Deferred();

	this.validate().done( () => {
		const path = this.instanceData.sfi_path,
			data = {
				displayName: this.nameInput.getValue(),
				metadata: {
					desc: this.descriptionInput.getValue(),
					group: this.groupInput.getValue(),
					keywords: this.keywordsInput.getValue(),
					notsearchable: !this.searchable.getValue(),
					instanceColor: this.color.getValue()
				},
				language: this.language.getValue()
			};

		dfd.resolve( path, data );
	} );

	return dfd.promise();
};

ext.bluespiceWikiFarm.ui.EditPanel.prototype.generateSubmitUrl = function ( path ) {
	const url = '/bluespice/farm/v1/instance/edit/' + path;
	return mw.util.wikiScript( 'rest' ) + url;
};

ext.bluespiceWikiFarm.ui.EditPanel.prototype.validate = function () {
	const dfd = $.Deferred();
	if ( this.inputValidity.name ) {
		dfd.resolve();
	} else {
		dfd.reject();
	}
	return dfd.promise();
};

ext.bluespiceWikiFarm.ui.EditPanel.prototype.onSubmit = function () {
	this.msgPanel.$element.empty();
	this.generateSubmitData().done( ( path, data ) => {
		$.ajax( {
			method: 'POST',
			url: this.generateSubmitUrl( path ),
			data: JSON.stringify( data ),
			contentType: 'application/json',
			dataType: 'json'
		} ).done( ( response ) => {
			this.onSubmitDone( response, true );
		} ).fail( ( jgXHR, type, status ) => {
			this.onSubmitDone( { type: type, status: status }, false );
		} );
	} );
};

ext.bluespiceWikiFarm.ui.EditPanel.prototype.onSubmitDone = function ( response, success ) {
	if ( success ) {
		// Fallback
		window.location.href = this.getManagementUrl();
	} else {
		this.msgPanel.$element.append(
			new OO.ui.MessageWidget( {
				type: 'error',
				label: mw.msg( 'wikifarm-instance-create-edit-error' )
			} ).$element
		);
	}
};

ext.bluespiceWikiFarm.ui.EditPanel.prototype.openTaskDialog = function ( action ) {
	let dialog;
	switch ( action ) {
		case 'remove':
			dialog = new ext.bluespiceWikiFarm.ui.dialog.RemoveInstanceDialog( { path: this.instanceData.sfi_path } );
			break;
		case 'suspend':
			dialog = new ext.bluespiceWikiFarm.ui.dialog.ConfirmDialog( {
				path: this.instanceData.sfi_path,
				action: 'suspend',
				title: mw.message( 'wikifarm-suspend-instance' ).plain(),
				prompt: mw.message( 'wikifarm-suspend-prompt' ).plain()
			} );
			break;
		case 'resume':
			dialog = new ext.bluespiceWikiFarm.ui.dialog.ConfirmDialog( {
				path: this.instanceData.sfi_path,
				action: 'resume',
				title: mw.message( 'wikifarm-resume-instance' ).plain(),
				prompt: mw.message( 'wikifarm-resume-prompt' ).plain()
			} );
			break;
	}

	if ( dialog ) {
		const windowManager = new OO.ui.WindowManager();
		$( document.body ).append( windowManager.$element );
		windowManager.addWindows( [ dialog ] );
		windowManager.openWindow( dialog ).closed.then( ( res ) => {
			$( document.body ).remove( windowManager.$element );
			if ( res && res.needsReload ) {
				if ( res.goToOverview ) {
					window.location.href = this.getManagementUrl();
				} else {
					window.location.reload();
				}
			}
		} );
	}
};

ext.bluespiceWikiFarm.ui.EditPanel.prototype.getManagementUrl = function ( action, params ) {
	if ( !action ) {
		return mw.Title.makeTitle( -1, mw.config.get( 'wgCanonicalSpecialPageName' ) ).getUrl();
	}
	const page = mw.Title.makeTitle( -1, mw.config.get( 'wgCanonicalSpecialPageName' ) + '/' + action );
	return page.getUrl( params || {} );
};

ext.bluespiceWikiFarm.ui.EditPanel.prototype.generatePath = function ( name ) {
	// Make ajax call, but first cancel any previous call
	if ( this.generatePathRequest ) {
		this.generatePathRequest.abort();
	}
	const deferred = $.Deferred();
	this.generatePathRequest = $.ajax( {
		url: mw.util.wikiScript( 'rest' ) + '/bluespice/farm/v1/generate_path',
		data: {
			name: name
		}
	} ).done( ( data ) => {
		deferred.resolve( data );
	} ).fail( () => {
		deferred.reject();
	} );

	return deferred.promise();
};

var farmActionButton = function ( config ) { // eslint-disable-line no-var
	config = config || {};
	config.classes = config.classes || [];
	config.classes.push( 'action-button' );
	farmActionButton.super.call( this, config );
	if ( config.help ) {
		new OO.ui.LabelWidget(
			{
				label: config.help,
				classes: [ 'ext-bluespiceWikiFarm-button-with-label-help' ]
			}
		).$element.insertAfter( this.$label );
	}
};

OO.inheritClass( farmActionButton, OO.ui.ButtonWidget );
