ext.bluespiceWikiFarm.ui.dialog.TaskDialog = function ( config ) {
	config = config || {};
	ext.bluespiceWikiFarm.ui.dialog.TaskDialog.parent.call( this, config );
	this.item = config.item || {};
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.dialog.TaskDialog, OO.ui.ProcessDialog );

ext.bluespiceWikiFarm.ui.dialog.TaskDialog.static.name = 'taskDialog';

ext.bluespiceWikiFarm.ui.dialog.TaskDialog.prototype.getSetupProcess = function () {
	return ext.bluespiceWikiFarm.ui.dialog.TaskDialog.parent.prototype.getSetupProcess.call( this ).next( function () {
		this.actions.setAbilities( { submit: false, close: true } );
	}, this );
};

ext.bluespiceWikiFarm.ui.dialog.TaskDialog.prototype.initialize = function () {
	ext.bluespiceWikiFarm.ui.dialog.TaskDialog.parent.prototype.initialize.apply( this, arguments );
	this.panel = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );
	this.actions.setAbilities( { submit: false } );
	this.addItems();
	this.$body.append( this.panel.$element );
};

ext.bluespiceWikiFarm.ui.dialog.TaskDialog.prototype.addItems = function () {
	// STUB
};

ext.bluespiceWikiFarm.ui.dialog.TaskDialog.prototype.getActionProcess = function ( action ) {
	if ( action === 'close' ) {
		return new OO.ui.Process( function () {
			this.close( { needsReload: false } );
		}, this );
	}
	if ( action === 'submit' ) {
		return new OO.ui.Process( function () {
			const dfd = $.Deferred();
			this.actions.setAbilities( { submit: false, close: false } );
			this.checkValidity().done( () => {
				this.makeProcessPanel();
				this.startProcess().done( ( id ) => {
					this.popPending();
					this.waitForProcess( id ).done( () => {
						this.close( { needsReload: this.needsReload(), goToOverview: this.shouldGoToOverview() } );
					} ).fail( () => {
						dfd.reject(
							new OO.ui.Error( mw.message( 'wikifarm-error-generic' ).text(),
								{ recoverable: false } )
						);
					} );
				} ).fail( () => {
					this.popPending();
					dfd.reject(
						new OO.ui.Error( mw.message( 'wikifarm-error-generic' ).text(),
							{ recoverable: false } )
					);
				} );
			} ).fail( () => {
				this.popPending();
			} );

			return dfd.promise();
		}, this );
	}
	return ext.bluespiceWikiFarm.ui.dialog.TaskDialog.parent.prototype.getActionProcess.call( this, action );
};

ext.bluespiceWikiFarm.ui.dialog.TaskDialog.prototype.startProcess = function () {
	// Override this
	return $.Deferred().reject().promise();
};

ext.bluespiceWikiFarm.ui.dialog.TaskDialog.prototype.needsReload = function () {
	return true;
};

ext.bluespiceWikiFarm.ui.dialog.TaskDialog.prototype.shouldGoToOverview = function () {
	return false;
};

ext.bluespiceWikiFarm.ui.dialog.TaskDialog.prototype.waitForProcess = function ( id, dfd ) {
	dfd = dfd || $.Deferred();
	this.checkProcess( id ).done( () => {
		dfd.resolve();
	} ).fail( () => {
		dfd.reject();
	} );

	return dfd.promise();
};

ext.bluespiceWikiFarm.ui.dialog.TaskDialog.prototype.makeProcessPanel = function () {
	this.panel.$element.children().remove();
	const progressBar = new OO.ui.ProgressBarWidget( { progress: false } );
	this.panel.$element.append( progressBar.$element );
	this.updateSize();

	this.stepIcons = {};
	this.stepsInitialized = false;
	this.stepProgress = 0;

	this.connect( this, {
		stepsRetrieved: function ( steps ) {
			if ( this.stepsInitialized ) {
				return;
			}
			progressBar.setProgress( 0 );
			this.stepProgress = 100 / Object.keys( steps ).length;
			for ( const id in steps ) {
				this.stepIcons[ id ] = new OO.ui.IconWidget( { icon: 'clock' } );
				new OO.ui.HorizontalLayout( {
					items: [
						this.stepIcons[ id ],
						new OO.ui.LabelWidget( { label: steps[ id ] } )
					]
				} ).$element.insertBefore( progressBar.$element );
			}
			this.stepsInitialized = true;
			this.updateSize();
		},
		stepCompleted: function ( step ) {
			if ( this.stepIcons.hasOwnProperty( step ) ) {
				this.stepIcons[ step ].setIcon( 'check' );
				progressBar.setProgress( ( progressBar.getProgress() || 0 ) + this.stepProgress );
			}
		}
	} );
};

ext.bluespiceWikiFarm.ui.dialog.TaskDialog.prototype.checkProcess = function ( id ) {
	const dfd = $.Deferred();
	this.ajax( 'process/' + id, 'GET' ).done( ( response ) => {
		this.emit( 'stepsRetrieved', response.steps );
		if ( response.state === 'failed' ) {
			dfd.reject();
		} else if ( response.state === 'success' ) {
			dfd.resolve();
		} else {
			if ( response.hasOwnProperty( 'doneStep' ) ) {
				this.emit( 'stepCompleted', response.doneStep );
			}
			setTimeout( () => {
				this.waitForProcess( id, dfd );
			}, 1000 );
		}
	} ).fail( () => {
		dfd.reject();
	} );
	return dfd.promise();
};

ext.bluespiceWikiFarm.ui.dialog.TaskDialog.prototype.onDismissErrorButtonClick = function () {
	this.close();
};

ext.bluespiceWikiFarm.ui.dialog.TaskDialog.prototype.getBodyHeight = function () {
	if ( !this.$errors.hasClass( 'oo-ui-element-hidden' ) ) {
		return this.$element.find( '.oo-ui-processDialog-errors' )[ 0 ].scrollHeight;
	}
	return this.$element.find( '.oo-ui-window-body' )[ 0 ].scrollHeight;
};

ext.bluespiceWikiFarm.ui.dialog.TaskDialog.prototype.showErrors = function ( errors ) {
	ext.bluespiceWikiFarm.ui.dialog.TaskDialog.parent.prototype.showErrors.call( this, errors );
	this.panel.$element.children().remove();
	this.updateSize();
};

ext.bluespiceWikiFarm.ui.dialog.TaskDialog.prototype.checkValidity = function () {
	return $.Deferred().resolve().promise();
};

ext.bluespiceWikiFarm.ui.dialog.TaskDialog.prototype.ajax = function ( path, method, data ) {
	data = data || {};
	const dfd = $.Deferred();

	$.ajax( {
		method: method,
		url: mw.util.wikiScript( 'rest' ) + '/bluespice/farm/v1/' + path,
		data: JSON.stringify( data ),
		contentType: 'application/json',
		dataType: 'json'

	} ).done( ( response ) => {
		dfd.resolve( response );
	} ).fail( ( jgXHR, type, status ) => {
		if ( type === 'error' ) {
			dfd.reject( {
				error: jgXHR.responseJSON || jgXHR.responseText
			} );
		}
		dfd.reject( { type: type, status: status } );
	} );

	return dfd.promise();
};
