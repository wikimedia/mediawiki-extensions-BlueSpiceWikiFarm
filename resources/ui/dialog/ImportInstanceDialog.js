ext.bluespiceWikiFarm.ui.dialog.ImportInstanceDialog = function ( config ) {
	ext.bluespiceWikiFarm.ui.dialog.ImportInstanceDialog.parent.call( this, config );
	this.packageName = null;
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.dialog.ImportInstanceDialog, ext.bluespiceWikiFarm.ui.dialog.TaskDialog );

ext.bluespiceWikiFarm.ui.dialog.ImportInstanceDialog.static.title = mw.message( 'wikifarm-import-instance' ).plain();
ext.bluespiceWikiFarm.ui.dialog.ImportInstanceDialog.static.actions = [
	{
		action: 'close',
		label: mw.message( 'wikifarm-button-action-label-cancel' ).text(),
		flags: [ 'safe', 'close' ],
		modes: [ 'upload', 'import' ]
	},
	{
		action: 'verify',
		label: mw.message( 'wikifarm-button-action-label-verify' ).text(),
		flags: [ 'progressive', 'primary' ],
		modes: [ 'upload' ]
	},
	{
		action: 'submit',
		label: mw.message( 'wikifarm-button-action-label-import' ).text(),
		flags: [ 'progressive', 'primary' ],
		modes: [ 'import' ]
	}
];

ext.bluespiceWikiFarm.ui.dialog.ImportInstanceDialog.prototype.getSetupProcess = function () {
	return ext.bluespiceWikiFarm.ui.dialog.ImportInstanceDialog.parent.prototype.getSetupProcess.call( this ).next( function () {
		this.actions.setAbilities( { submit: false, close: true } );
		this.setPanel( 'upload' );
	}, this );
};

ext.bluespiceWikiFarm.ui.dialog.ImportInstanceDialog.prototype.addItems = function () {
	this.actions.setAbilities( { submit: false } );

	this.uploadPanel = new OO.ui.PanelLayout( {
		expanded: false,
		padded: false
	} );
	this.verificationResultPanel = new OO.ui.PanelLayout( {
		expanded: false,
		padded: false
	} );

	this.uploadWidget = new OO.ui.SelectFileInputWidget( {
		showDropTarget: true,
		placeholder: mw.msg( 'wikifarm-import-select-file' )
	} );
	this.checksumInput = new OO.ui.TextInputWidget( {} );
	this.checksumLayout = new OO.ui.FieldLayout( this.checksumInput, {
		label: mw.message( 'wikifarm-import-checksum' ).text(),
		align: 'top'
	} );
	this.uploadPanel.$element.append( this.uploadWidget.$element, this.checksumLayout.$element );

	this.panel.$element.append( this.uploadPanel.$element, this.verificationResultPanel.$element );

	this.setPanel( 'upload' );
};

ext.bluespiceWikiFarm.ui.dialog.ImportInstanceDialog.prototype.getActionProcess = function ( action ) {
	if ( action === 'verify' ) {
		return new OO.ui.Process( function () {
			const dfd = $.Deferred();
			const file = this.getSelectedFile();
			if ( !file ) {
				dfd.reject( new OO.ui.Error( mw.message( 'wikifarm-error-generic' ).text(), { recoverable: true } ) );
				return dfd.promise();
			}

			this.actions.setAbilities( { verify: false, close: false, submit: false } );
			this.verifyPackage().done( ( response ) => {
				this.packageName = response.packageName || null;
				this.renderVerificationResult( response );
				this.setPanel( 'import' );
				this.actions.setAbilities( { verify: true, close: true, submit: !!this.packageName } );
				dfd.resolve();
			} ).fail( ( err ) => {
				this.actions.setAbilities( { verify: true, close: true, submit: false } );
				dfd.reject(
					new OO.ui.Error( this.extractErrorMessage( err ), { recoverable: true } )
				);
			} );
			return dfd.promise();
		}, this );
	}
	return ext.bluespiceWikiFarm.ui.dialog.ImportInstanceDialog.parent.prototype.getActionProcess.call( this, action );
};

ext.bluespiceWikiFarm.ui.dialog.ImportInstanceDialog.prototype.setPanel = function ( panel ) {
	this.actions.setMode( panel );
	if ( panel === 'upload' ) {
		this.uploadPanel.$element.show();
		this.verificationResultPanel.$element.hide();
	} else {
		this.uploadPanel.$element.hide();
		this.verificationResultPanel.$element.show();
	}
	this.updateSize();
};

ext.bluespiceWikiFarm.ui.dialog.ImportInstanceDialog.prototype.startProcess = function () {
	const dfd = $.Deferred();
	this.ajax( 'import/execute/' + this.packageName, 'POST' ).done( ( response ) => {
		dfd.resolve( response.value );
	} ).fail( ( err ) => {
		dfd.reject( err );
	} );

	this.actions.setAbilities( { submit: false, close: true } );

	return dfd.promise();
};

ext.bluespiceWikiFarm.ui.dialog.ImportInstanceDialog.prototype.checkValidity = function () {
	if ( !this.packageName ) {
		return $.Deferred().reject().promise();
	}
	return $.Deferred().resolve().promise();
};

ext.bluespiceWikiFarm.ui.dialog.ImportInstanceDialog.prototype.onProcessComplete = function ( response ) {
	const output = response.rawOutput || {};
	if ( !output || !output.setInstanceReady ) {
		this.panel.$element.html(
			new OO.ui.MessageWidget( {
				type: 'error',
				label: mw.msg( 'wikifarm-import-fail' )
			} ).$element
		);
		return;
	}
	this.panel.$element.empty();
	this.panel.$element.append(
		new OO.ui.MessageWidget( {
			type: 'success',
			label: mw.msg( 'wikifarm-import-success', output.instanceName )
		} ).$element
	);
	this.panel.$element.append( new OO.ui.ButtonWidget( {
		label: output.instanceName,
		framed: false,
		flags: [ 'primary', 'progressive' ],
		href: output.instanceUrl,
		target: '_blank'
	} ).$element.css( 'margin-left', '0' ) );

	this.updateSize();
};

ext.bluespiceWikiFarm.ui.dialog.ImportInstanceDialog.prototype.verifyPackage = function () {
	const dfd = $.Deferred();
	const file = this.getSelectedFile();
	if ( !file ) {
		dfd.reject( { error: mw.message( 'wikifarm-error-generic' ).text() } );
		return dfd.promise();
	}
	const checksum = this.checksumInput.getValue().trim();
	const formData = new FormData();
	formData.append( 'file', file );
	if ( checksum !== '' ) {
		formData.append( 'checksum', checksum );
	}

	$.ajax( {
		method: 'POST',
		url: mw.util.wikiScript( 'rest' ) + '/bluespice/farm/v1/import/verify',
		data: formData,
		processData: false,
		contentType: false,
		dataType: 'json'
	} ).done( ( response ) => {
		dfd.resolve( response );
	} ).fail( ( jqXHR, type, status ) => {
		if ( type === 'error' ) {
			dfd.reject( { error: jqXHR.responseJSON || jqXHR.responseText } );
			return;
		}
		dfd.reject( { type: type, status: status } );
	} );

	return dfd.promise();
};

ext.bluespiceWikiFarm.ui.dialog.ImportInstanceDialog.prototype.getSelectedFile = function () {
	if ( this.uploadWidget.$input && this.uploadWidget.$input[ 0 ] && this.uploadWidget.$input[ 0 ].files ) {
		return this.uploadWidget.$input[ 0 ].files[ 0 ] || null;
	}
	const value = this.uploadWidget.getValue();
	if ( value instanceof File ) {
		return value;
	}
	return null;
};

ext.bluespiceWikiFarm.ui.dialog.ImportInstanceDialog.prototype.renderVerificationResult = function ( response ) {
	this.verificationResultPanel.$element.empty();
	if ( response.valid ) {
		this.verificationResultPanel.$element.append( new OO.ui.MessageWidget( {
			type: 'success',
			label: mw.msg( 'wikifarm-import-verification-success' )
		} ).$element );
	} else {
		this.verificationResultPanel.$element.append( new OO.ui.MessageWidget( {
			type: 'error',
			label: mw.msg( 'wikifarm-import-verification-fail' )
		} ).$element );
		this.actions.setAbilities( { submit: false } );
	}
	if ( Array.isArray( response.warnings ) ) {
		response.warnings.forEach( ( warning ) => {
			this.verificationResultPanel.$element.append( new OO.ui.MessageWidget( {
				type: 'warning',
				label: warning
			} ).$element );
		} );
	}
};

ext.bluespiceWikiFarm.ui.dialog.ImportInstanceDialog.prototype.extractErrorMessage = function ( err ) {
	const fallback = mw.message( 'wikifarm-error-generic' ).text();
	if ( !err || !err.error ) {
		return fallback;
	}
	const translations = err.error.messageTranslations;
	if ( translations && typeof translations === 'object' ) {
		const lang = mw.config.get( 'wgUserLanguage' );
		const keys = Object.keys( translations );
		if ( translations[ lang ] ) {
			return translations[ lang ];
		}
		if ( translations.en ) {
			return translations.en;
		}
		if ( keys.length ) {
			return translations[ keys[ 0 ] ];
		}
	}
	if ( err.error.message ) {
		return err.error.message;
	}
	if ( typeof err.error === 'string' ) {
		return err.error;
	}
	return fallback;
};
