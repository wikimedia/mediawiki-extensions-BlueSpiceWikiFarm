ext.bluespiceWikiFarm.ui.dialog.RemoveInstanceDialog = function ( config ) {
	ext.bluespiceWikiFarm.ui.dialog.RemoveInstanceDialog.parent.call( this, config );
	this.path = config.path;
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.dialog.RemoveInstanceDialog, ext.bluespiceWikiFarm.ui.dialog.TaskDialog );

ext.bluespiceWikiFarm.ui.dialog.RemoveInstanceDialog.static.title = mw.message( 'wikifarm-remove-instance' ).plain();
ext.bluespiceWikiFarm.ui.dialog.RemoveInstanceDialog.static.actions = [
	{ action: 'close', label: mw.message( 'wikifarm-button-action-label-cancel' ).text(), flags: 'safe' },
	{
		action: 'submit',
		label: mw.message( 'wikifarm-button-action-label-delete' ).text(),
		flags: [ 'destructive', 'primary' ]
	}
];

ext.bluespiceWikiFarm.ui.dialog.RemoveInstanceDialog.prototype.addItems = function () {
	this.actions.setAbilities( { submit: false } );
	this.pathConfirmInput = new OO.ui.TextInputWidget( {
		required: true,
		validate: function ( val ) {
			return val === this.path;
		}.bind( this )
	} );
	this.pathConfirmInput.connect( this, {
		change: function () {
			this.checkValidity();
		}
	} );

	this.panel.$element.append(
		new OO.ui.LabelWidget( {
			label: new OO.ui.HtmlSnippet( mw.message( 'wikifarm-remove-instructions' ).plain() + '<code>' + this.path + '</code>' )

		} ).$element,
		new OO.ui.FieldLayout( this.pathConfirmInput, {
			label: mw.message( 'wikifarm-instance-path' ).plain()
		} ).$element
	);
};

ext.bluespiceWikiFarm.ui.dialog.RemoveInstanceDialog.prototype.shouldGoToOverview = function () {
	return true;
};

ext.bluespiceWikiFarm.ui.dialog.RemoveInstanceDialog.prototype.startProcess = function () {
	const dfd = $.Deferred();
	this.ajax( 'instance/' + this.path, 'DELETE' ).done( ( response ) => {
		dfd.resolve( response.value );
	} ).fail( ( err ) => {
		dfd.reject( err );
	} );

	return dfd.promise();
};

ext.bluespiceWikiFarm.ui.dialog.RemoveInstanceDialog.prototype.checkValidity = function () {
	const dfd = $.Deferred();
	this.pathConfirmInput.getValidity().done( () => {
		this.actions.setAbilities( { submit: true } );
		dfd.resolve();
	} ).fail( () => {
		this.actions.setAbilities( { submit: false } );
		dfd.reject();
	} );
	return dfd.promise();
};
