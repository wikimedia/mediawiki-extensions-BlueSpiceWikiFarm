ext.bluespiceWikiFarm.ui.dialog.CreateTeamDialog = function ( config ) {
	config = config || {};
	ext.bluespiceWikiFarm.ui.dialog.CreateTeamDialog.parent.call( this, config );
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.dialog.CreateTeamDialog, OO.ui.ProcessDialog );

ext.bluespiceWikiFarm.ui.dialog.CreateTeamDialog.static.name = 'createTeamDialog';
ext.bluespiceWikiFarm.ui.dialog.CreateTeamDialog.static.title = mw.msg( 'wikifarm-ui-teams-action-create-team' );
ext.bluespiceWikiFarm.ui.dialog.CreateTeamDialog.static.actions = [
	{ action: 'close', label: mw.message( 'wikifarm-button-action-label-cancel' ).text(), flags: 'safe' },
	{
		action: 'submit',
		label: mw.message( 'wikifarm-button-action-label-add' ).text(),
		flags: [ 'progressive', 'primary' ]
	}
];

ext.bluespiceWikiFarm.ui.dialog.CreateTeamDialog.prototype.getSetupProcess = function () {
	return ext.bluespiceWikiFarm.ui.dialog.CreateTeamDialog.parent.prototype.getSetupProcess.call( this ).next( function () {
		this.actions.setAbilities( { submit: false, close: true } );
	}, this );
};

ext.bluespiceWikiFarm.ui.dialog.CreateTeamDialog.prototype.initialize = function () {
	ext.bluespiceWikiFarm.ui.dialog.CreateTeamDialog.parent.prototype.initialize.apply( this, arguments );
	this.panel = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );
	this.actions.setAbilities( { submit: false } );
	this.addItems();
	this.$body.append( this.panel.$element );
};

ext.bluespiceWikiFarm.ui.dialog.CreateTeamDialog.prototype.addItems = function () {
	this.nameInput = new OO.ui.TextInputWidget( {
		required: true,
		maxLength: 200
	} );
	this.descriptionInput = new OO.ui.MultilineTextInputWidget( {
		maxLength: 255,
		rows: 3,
		allowLinebreaks: false,
		maxRows: 3
	} );
	this.panel.$element.append(
		new OO.ui.FieldLayout( this.nameInput, {
			label: mw.message( 'wikifarm-ui-teams-header-name' ).text(),
			align: 'top'
		} ).$element,
		new OO.ui.FieldLayout( this.descriptionInput, {
			label: mw.message( 'wikifarm-ui-teams-header-description' ).text(),
			align: 'top'
		} ).$element
	);
	this.nameInput.connect( this, {
		change: function () {
			this.checkValidity();
		}
	} );
};

ext.bluespiceWikiFarm.ui.dialog.CreateTeamDialog.prototype.getActionProcess = function ( action ) {
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
			this.checkValidity().done( () => {
				const name = this.nameInput.getValue();
				const description = this.descriptionInput.getValue();
				$.ajax( {
					url: mw.util.wikiScript( 'rest' ) + '/bluespice/farm/v1/teams/' + encodeURIComponent( name ),
					type: 'PUT',
					data: JSON.stringify( {
						description: description
					} ),
					dataType: 'json',
					contentType: 'application/json; charset=UTF-8'
				} ).done( ( data ) => { // eslint-disable-line no-unused-vars
					this.close( { action: 'submit', name: name } );
					dfd.resolve();
				} ).fail( () => {
					this.popPending();
					dfd.reject();
				} );
			} ).fail( () => {
				this.popPending();
			} );

			return dfd.promise();
		}, this );
	}
	return ext.bluespiceWikiFarm.ui.dialog.CreateTeamDialog.parent.prototype.getActionProcess.call( this, action );
};

ext.bluespiceWikiFarm.ui.dialog.CreateTeamDialog.prototype.checkValidity = function () {
	const dfd = $.Deferred();
	this.nameInput.getValidity().done( () => {
		this.actions.setAbilities( { submit: true } );
		dfd.resolve();
	} ).fail( () => {
		this.actions.setAbilities( { submit: false } );
		dfd.reject();
	} );
	return dfd.promise();
};

ext.bluespiceWikiFarm.ui.dialog.CreateTeamDialog.prototype.getBodyHeight = function () {
	if ( !this.$errors.hasClass( 'oo-ui-element-hidden' ) ) {
		return this.$element.find( '.oo-ui-processDialog-errors' )[ 0 ].scrollHeight;
	}
	return this.$element.find( '.oo-ui-window-body' )[ 0 ].scrollHeight;
};

ext.bluespiceWikiFarm.ui.dialog.CreateTeamDialog.prototype.showErrors = function ( errors ) {
	ext.bluespiceWikiFarm.ui.dialog.CreateTeamDialog.parent.prototype.showErrors.call( this, errors );
	this.updateSize();
};
