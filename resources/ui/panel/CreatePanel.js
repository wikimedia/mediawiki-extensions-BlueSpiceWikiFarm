ext.bluespiceWikiFarm.ui.CreatePanel = function ( config ) {
	config = config || {};
	this.name = config.name || '';
	this.path = config.path || '';
	this.template = config.template || '_blank';
	this.availableTemplates = config.templates || {};
	this.presetSource = config.source || '';
	this.globalAccessEnabled = config.globalAccessEnabled || false;

	ext.bluespiceWikiFarm.ui.CreatePanel.parent.call( this, config );
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.CreatePanel, ext.bluespiceWikiFarm.ui.EditPanel );

ext.bluespiceWikiFarm.ui.CreatePanel.prototype.render = function () {
	this.templatePicker = new ext.bluespiceWikiFarm.ui.TemplatePicker( {
		availableTemplates: this.availableTemplates
	} );
	this.$element.append( this.templatePicker.$element );
	this.dataPanel.$element.append( this.makeInputs() );
	this.nameInput.setValue( this.name || this.path );
	this.templatePicker.picker.connect( this, { select: 'onTemplateSelect' } );
	if ( this.template ) {
		this.templatePicker.picker.selectItemByData( this.template );
		if ( this.template === '_clone' && this.presetSource ) {
			this.source.selectFromPath( this.presetSource );
		}
	}
	this.makeSubmitButtons();
};

ext.bluespiceWikiFarm.ui.CreatePanel.prototype.makeInputs = function () {
	ext.bluespiceWikiFarm.ui.EditPanel.prototype.makeInputs.call( this );

	this.source = new ext.bluespiceWikiFarm.ui.InstancePicker( {} );
	this.sourceLayout = new OO.ui.FieldLayout( this.source, {
		label: mw.message( 'wikifarm-instance-sourcepath' ).plain(),
		align: 'top',
		hidden: true
	} );
	this.pathInput = new ext.bluespiceWikiFarm.ui.PathInput( {} );
	this.pathInput.connect( this, { validityChange: 'onPathValidityChange' } );
	this.pathLayout = new OO.ui.FieldLayout( this.pathInput, {
		label: mw.message( 'wikifarm-instance-path' ).plain()
	} );
	if ( this.path ) {
		this.pathInput.setValue( this.path );
		this.pathInput.setDisabled( true );
	}

	this.accessLevel = new ext.bluespiceWikiFarm.ui.widget.AccessLevelInput( {} );

	const items = [
		this.nameLayout.$element,
		this.sourceLayout.$element,
		new OO.ui.FieldLayout( this.descriptionInput, {
			align: 'top',
			label: mw.message( 'wikifarm-instance-desc' ).plain()
		} ).$element,
		this.pathLayout.$element,
		new OO.ui.FieldLayout( this.searchable, {
			align: 'left',
			label: mw.message( 'wikifarm-instance-notsearchable' ).plain()
		} ).$element,
		new OO.ui.FieldLayout( this.groupInput, {
			label: mw.message( 'wikifarm-instance-group' ).plain()
		} ).$element,
		new OO.ui.FieldLayout( this.keywordsInput, {
			label: mw.message( 'wikifarm-instance-keywords' ).plain()
		} ).$element,
		new OO.ui.FieldLayout( this.language, {
			label: mw.message( 'wikifarm-lang-code' ).text()
		} ).$element
	];
	if ( this.globalAccessEnabled ) {
		items.push(
			new OO.ui.FieldLayout( this.accessLevel, {
				label: mw.message( 'wikifarm-ui-access-wiki-type-label' ).text()
			} ).$element
		);
	}
	return items;
};

ext.bluespiceWikiFarm.ui.CreatePanel.prototype.onTemplateSelect = function ( item ) {
	this.template = item.getData();
	if ( this.template === '_clone' ) {
		this.source.setRequired( true );
		this.sourceLayout.$element.show();
	} else {
		this.source.setRequired( false );
		this.sourceLayout.$element.hide();
	}
};

ext.bluespiceWikiFarm.ui.CreatePanel.prototype.onBeforeNameSet = function () {
	this.pathInput.pushPending();
	this.pathInput.setDisabled( true );
	this.pathLayout.setErrors( [] );
	this.inputValidity.path = false;
};

ext.bluespiceWikiFarm.ui.CreatePanel.prototype.onAfterNameSet = function ( isEmpty, isValid, response ) {
	if ( this.path ) {
		this.inputValidity.path = isValid;
		return;
	}
	if ( isEmpty ) {
		this.nameInput.setValidityFlag( false );
		this.pathInput.clear();
		return;
	}
	if ( isValid ) {
		this.inputValidity.path = true;
		this.pathInput.setCheckValidity( false );
		this.pathInput.clear();
		this.pathInput.setValue( response.path || '' );
		this.pathInput.setCheckValidity( true );
	} else {
		this.pathInput.clear();
		this.pathLayout.setErrors( [ mw.msg( 'wikifarm-instance-path-error' ) ] );
		this.pathInput.setValidityFlag( false );
	}
};

ext.bluespiceWikiFarm.ui.CreatePanel.prototype.getSubmitButtonLabel = function () {
	return mw.message( 'wikifarm-button-action-label-create' ).plain();
};

ext.bluespiceWikiFarm.ui.CreatePanel.prototype.onPathValidityChange = function ( valid ) {
	if ( valid === undefined ) {
		// not called by us
		return;
	}
	this.inputValidity.path = valid;
	if ( valid ) {
		this.pathLayout.setErrors( [] );
	} else {
		this.pathLayout.setErrors( [ mw.msg( 'wikifarm-instance-path-invalid' ) ] );
	}
};

ext.bluespiceWikiFarm.ui.CreatePanel.prototype.onSubmitDone = function ( response, success ) {
	if ( success ) {
		if ( response.hasOwnProperty( 'instanceUrl' ) ) {
			window.location.href = response.instanceUrl;
			return;
		}
	}
	ext.bluespiceWikiFarm.ui.CreatePanel.parent.prototype.onSubmitDone.call( this, response, success );
};

ext.bluespiceWikiFarm.ui.CreatePanel.prototype.generateSubmitData = function () {
	const dfd = $.Deferred();

	this.validate().done( () => {
		const path = this.pathInput.getValue(),
			data = {
				displayName: this.nameInput.getValue(),
				metadata: {
					desc: this.descriptionInput.getValue(),
					group: this.groupInput.getValue(),
					keywords: this.keywordsInput.getValue(),
					notsearchable: !this.searchable.getValue()
				},
				config: {},
				language: this.language.getValue()
			};
		if ( this.globalAccessEnabled ) {
			const accessLevel = this.accessLevel.findSelectedItem();
			if ( accessLevel ) {
				data.config.wgWikiFarmInitialAccessLevel = accessLevel.getData();
			}
		}
		if ( this.template !== '_blank' && this.template !== '_clone' ) {
			data.template = this.template;
		}

		dfd.resolve( path, data );
	} );

	return dfd.promise();
};

ext.bluespiceWikiFarm.ui.CreatePanel.prototype.generateSubmitUrl = function ( path ) {
	let url = '/bluespice/farm/v1/instance/' + path;
	if ( this.template === '_clone' ) {
		url = '/bluespice/farm/v1/instance/clone/' + this.source.selectedItem.data.path + '/' + path;
	}

	return mw.util.wikiScript( 'rest' ) + url;
};

ext.bluespiceWikiFarm.ui.CreatePanel.prototype.validate = function () {
	const dfd = $.Deferred();
	if ( this.template === '_clone' && ( !this.source.selectedItem || !this.source.selectedItem.data.path ) ) {
		this.source.setValidityFlag( false );
		dfd.reject();
		return dfd.promise();
	}
	if ( this.inputValidity.path && this.inputValidity.name ) {
		dfd.resolve();
	} else {
		dfd.reject();
	}
	return dfd.promise();
};
