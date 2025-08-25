ext.bluespiceWikiFarm.ui.dialog.PromoteToSharedDialog = function ( config ) {
	config = config || {};
	this.pageId = config.pageId || null;
	this.wasMoved = false;
	ext.bluespiceWikiFarm.ui.dialog.PromoteToSharedDialog.parent.call( this, config );
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.dialog.PromoteToSharedDialog, OO.ui.ProcessDialog );

ext.bluespiceWikiFarm.ui.dialog.PromoteToSharedDialog.static.name = 'promoteToSharedDialog';
ext.bluespiceWikiFarm.ui.dialog.PromoteToSharedDialog.static.title = mw.msg( 'wikifarm-promote-to-shared-dialog-title' );

ext.bluespiceWikiFarm.ui.dialog.PromoteToSharedDialog.static.actions = [
	{
		action: 'close',
		label: mw.msg( 'wikifarm-button-action-label-cancel' ),
		flags: [ 'safe', 'close' ]
	},
	{
		action: 'promote',
		label: mw.msg( 'wikifarm-button-action-label-promote-to-shared' ),
		flags: [ 'primary', 'destructive' ]
	}
];

ext.bluespiceWikiFarm.ui.dialog.PromoteToSharedDialog.prototype.initialize = function () {
	ext.bluespiceWikiFarm.ui.dialog.PromoteToSharedDialog.parent.prototype.initialize.apply( this, arguments );
	this.panel = new OO.ui.PanelLayout( { expanded: false, padded: true } );

	const msg = new OO.ui.MessageWidget( {
		type: 'notice',
		label: mw.msg( 'wikifarm-promote-to-shared-dialog-description' )
	} );
	this.panel.$element.append( msg.$element );

	this.steps = {
		push: this.makeStep( 'wikifarm-promote-to-shared-dialog-push' ),
		deleteLocal: this.makeStep( 'wikifarm-promote-to-shared-dialog-delete-local' )
	};
	this.stepLayout = new OO.ui.PanelLayout( { expanded: false, padded: true } );
	this.panel.$element.append( this.stepLayout.$element );
	this.stepLayout.$element.hide();

	for ( const step in this.steps ) {
		this.stepLayout.$element.append( this.steps[ step ].layout.$element );
	}

	this.$body.append( this.panel.$element );
};

ext.bluespiceWikiFarm.ui.dialog.PromoteToSharedDialog.prototype.getActionProcess = function ( action ) {
	return ext.bluespiceWikiFarm.ui.dialog.PromoteToSharedDialog.parent.prototype.getActionProcess.call( this, action )
		.next(
			function () {
				if ( action === 'promote' ) {
					this.pushPending();
					this.actions.setAbilities( { promote: false } );
					const dfd = $.Deferred();
					this.stepLayout.$element.show();
					this.updateSize();
					if ( !this.pageId ) {
						dfd.reject( new OO.ui.Error( mw.msg( 'wikifarm-error-unknown' ), { recoverable: false } ) );
						return dfd.promise();
					}
					const farmConfig = ext.bluespiceWikiFarm._config(); // eslint-disable-line no-underscore-dangle
					new mw.Api().postWithToken( 'csrf', {
						action: 'content-transfer-do-push-single',
						format: 'json',
						articleId: this.pageId,
						pushTarget: JSON.stringify( {
							id: farmConfig.sharedWikiPath,
							url: farmConfig.sharedWikiApiUrl,
							pushToDraft: false,
							authentication: []
						} )
					} ).done( () => {
						this.steps.push.icon.setIcon( 'check' );
						dfd.resolve();
					} ).fail( () => {
						this.steps.push.icon.setIcon( 'close' );
						this.popPending();
						console.error( arguments ); // eslint-disable-line no-console
						dfd.reject( new OO.ui.Error( mw.msg( 'wikifarm-error-unknown' ), { recoverable: false } ) );
					} );
					return dfd.promise();
				}
				if ( action === 'close' ) {
					this.close( this.wasMoved );
				}
			}, this )
		.next(
			function () {
				if ( action === 'promote' ) {
					const dfd = $.Deferred();
					new mw.Api().postWithToken( 'csrf', {
						action: 'delete',
						format: 'json',
						pageid: this.pageId,
						reason: mw.msg( 'wikifarm-promote-to-shared-dialog-delete-local-reason' ),
						watchlist: 'unwatch'
					} ).done( () => {
						this.steps.deleteLocal.icon.setIcon( 'check' );
						this.popPending();
						this.wasMoved = true;
						dfd.resolve();
					} ).fail( () => {
						this.steps.deleteLocal.icon.setIcon( 'close' );
						this.popPending();
						console.error( arguments ); // eslint-disable-line no-console
						dfd.reject( new OO.ui.Error( mw.msg( 'wikifarm-error-unknown' ), { recoverable: false } ) );
					} );
					return dfd.promise();
				}
			}, this
		);
};

ext.bluespiceWikiFarm.ui.dialog.PromoteToSharedDialog.prototype.makeStep = function ( labelMsg ) {
	const icon = new OO.ui.IconWidget( {
		icon: 'clock'
	} );
	const label = new OO.ui.LabelWidget( {
		label: mw.msg( labelMsg ) // eslint-disable-line mediawiki/msg-doc
	} );
	const layout = new OO.ui.HorizontalLayout( {
		items: [ icon, label ]
	} );

	return {
		layout: layout,
		icon: icon
	};
};

ext.bluespiceWikiFarm.ui.dialog.PromoteToSharedDialog.prototype.onDismissErrorButtonClick = function () {
	this.close();
};

ext.bluespiceWikiFarm.ui.dialog.PromoteToSharedDialog.prototype.getBodyHeight = function () {
	if ( !this.$errors.hasClass( 'oo-ui-element-hidden' ) ) {
		return this.$element.find( '.oo-ui-processDialog-errors' )[ 0 ].scrollHeight;
	}
	return this.$element.find( '.oo-ui-window-body' )[ 0 ].scrollHeight;
};

ext.bluespiceWikiFarm.ui.dialog.PromoteToSharedDialog.prototype.showErrors = function ( errors ) {
	ext.bluespiceWikiFarm.ui.dialog.PromoteToSharedDialog.parent.prototype.showErrors.call( this, errors );
	this.updateSize();
};
