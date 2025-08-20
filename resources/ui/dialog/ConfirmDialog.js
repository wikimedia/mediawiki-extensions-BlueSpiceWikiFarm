ext.bluespiceWikiFarm.ui.dialog.ConfirmDialog = function ( config ) {
	ext.bluespiceWikiFarm.ui.dialog.ConfirmDialog.parent.call( this, config );

	this.path = config.path;
	this.action = config.action;
	this.apiData = config.apiData;
	this.title = config.title;
	this.prompt = config.prompt;
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.dialog.ConfirmDialog, ext.bluespiceWikiFarm.ui.dialog.TaskDialog );

ext.bluespiceWikiFarm.ui.dialog.ConfirmDialog.static.actions = [
	{ action: 'close', label: mw.message( 'wikifarm-button-action-label-cancel' ).text(), flags: 'safe' },
	{
		action: 'save',
		label: mw.message( 'wikifarm-button-action-label-save' ).text(),
		flags: [ 'progressive', 'primary' ]
	}
];

ext.bluespiceWikiFarm.ui.dialog.ConfirmDialog.prototype.initialize = function () {
	ext.bluespiceWikiFarm.ui.dialog.ConfirmDialog.static.title = this.title;
	ext.bluespiceWikiFarm.ui.dialog.ConfirmDialog.parent.prototype.initialize.apply( this, arguments );
};

ext.bluespiceWikiFarm.ui.dialog.ConfirmDialog.prototype.addItems = function () {
	this.panel.$element.append( new OO.ui.LabelWidget( {
		label: this.prompt
	} ).$element );
};

ext.bluespiceWikiFarm.ui.dialog.ConfirmDialog.prototype.getActionProcess = function ( action ) {
	if ( action === 'save' ) {
		return new OO.ui.Process( function () {
			this.pushPending();
			const dfd = $.Deferred();
			this.actions.setAbilities( { save: false, close: false } );
			this.doExecute().done( () => {
				this.close( { needsReload: true } );
			} ).fail( () => {
				this.popPending();
				dfd.reject(
					new OO.ui.Error( mw.message( 'wikifarm-error-generic' ).text(),
						{ recoverable: false } )
				);
			} );

			return dfd.promise();
		}, this );
	}
	return ext.bluespiceWikiFarm.ui.dialog.ConfirmDialog.parent.prototype.getActionProcess.call( this, action );
};

ext.bluespiceWikiFarm.ui.dialog.ConfirmDialog.prototype.doExecute = function () {
	return this.ajax( 'instance/' + this.action + '/' + this.path, 'POST', this.apiData );

};
