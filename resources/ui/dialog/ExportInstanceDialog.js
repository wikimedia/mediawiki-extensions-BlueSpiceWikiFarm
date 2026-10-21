ext.bluespiceWikiFarm.ui.dialog.ExportInstanceDialog = function ( config ) {
	ext.bluespiceWikiFarm.ui.dialog.ExportInstanceDialog.parent.call( this, config );
	this.path = config.path;
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.dialog.ExportInstanceDialog, ext.bluespiceWikiFarm.ui.dialog.TaskDialog );

ext.bluespiceWikiFarm.ui.dialog.ExportInstanceDialog.static.title = mw.message( 'wikifarm-export-instance' ).plain();
ext.bluespiceWikiFarm.ui.dialog.ExportInstanceDialog.static.actions = [
	{
		action: 'close',
		label: mw.message( 'wikifarm-button-action-label-cancel' ).text(),
		flags: [ 'safe', 'close' ]
	},
	{
		action: 'submit',
		label: mw.message( 'wikifarm-button-action-label-export' ).text(),
		flags: [ 'progressive', 'primary' ]
	}
];

ext.bluespiceWikiFarm.ui.dialog.ExportInstanceDialog.prototype.getSetupProcess = function () {
	return ext.bluespiceWikiFarm.ui.dialog.ExportInstanceDialog.parent.prototype.getSetupProcess.call( this ).next( function () {
		this.actions.setAbilities( { submit: true, close: true } );
	}, this );
};

ext.bluespiceWikiFarm.ui.dialog.ExportInstanceDialog.prototype.addItems = function () {
	this.actions.setAbilities( { submit: false } );

	this.panel.$element.append(
		new OO.ui.MessageWidget( {
			type: 'info',
			label: new OO.ui.HtmlSnippet( mw.message( 'wikifarm-export-instructions', this.path ).parse() )
		} ).$element
	);

};

ext.bluespiceWikiFarm.ui.dialog.ExportInstanceDialog.prototype.startProcess = function () {
	const dfd = $.Deferred();
	this.ajax( 'instance/export/' + this.path, 'POST' ).done( ( response ) => {
		dfd.resolve( response.value );
	} ).fail( ( err ) => {
		dfd.reject( err );
	} );

	this.actions.setAbilities( { submit: false, close: true } );

	return dfd.promise();
};

ext.bluespiceWikiFarm.ui.dialog.ExportInstanceDialog.prototype.checkValidity = function () {
	return $.Deferred().resolve().promise();
};

ext.bluespiceWikiFarm.ui.dialog.ExportInstanceDialog.prototype.onProcessComplete = function ( response ) {
	const output = response.rawOutput || {};
	if ( !output || !output.packageDownloadUrl || !output.packageFile ) {
		this.panel.$element.html(
			new OO.ui.MessageWidget( {
				type: 'error',
				label: mw.msg( 'wikifarm-export-fail' )
			} ).$element
		);
		return;
	}
	this.panel.$element.empty();
	this.panel.$element.append(
		new OO.ui.MessageWidget( {
			type: 'success',
			label: mw.msg( 'wikifarm-export-success', this.path )
		} ).$element
	);
	this.panel.$element.append( new OO.ui.ButtonWidget( {
		label: output.packageFile,
		framed: false,
		flags: [ 'primary', 'progressive' ],
		href: output.packageDownloadUrl,
		target: '_blank'
	} ).$element.css( 'margin-left', '0' ) );

	this.panel.$element.append( new OO.ui.LabelWidget( {
		label: new OO.ui.HtmlSnippet( mw.message( 'wikifarm-export-checksum', output.mainChecksum ).parse() )
	} ).$element );

	this.updateSize();
};
