ext.bluespiceWikiFarm.ui.dialog.AddTeamMemberDialog = function ( config ) {
	config = config || {};
	this.team = config.team || {};
	ext.bluespiceWikiFarm.ui.dialog.AddTeamMemberDialog.parent.call( this, config );
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.dialog.AddTeamMemberDialog, OO.ui.ProcessDialog );

ext.bluespiceWikiFarm.ui.dialog.AddTeamMemberDialog.static.name = 'addTeamMember';
ext.bluespiceWikiFarm.ui.dialog.AddTeamMemberDialog.static.title = mw.msg( 'wikifarm-ui-teams-action-add-member' );
ext.bluespiceWikiFarm.ui.dialog.AddTeamMemberDialog.static.actions = [
	{ action: 'close', label: mw.message( 'wikifarm-button-action-label-cancel' ).text(), flags: 'safe' },
	{
		action: 'submit',
		label: mw.message( 'wikifarm-button-action-label-add' ).text(),
		flags: [ 'progressive', 'primary' ]
	}
];

ext.bluespiceWikiFarm.ui.dialog.AddTeamMemberDialog.prototype.getSetupProcess = function () {
	return ext.bluespiceWikiFarm.ui.dialog.AddTeamMemberDialog.parent.prototype.getSetupProcess.call( this ).next( function () {
		this.actions.setAbilities( { submit: false, close: true } );
	}, this );
};

ext.bluespiceWikiFarm.ui.dialog.AddTeamMemberDialog.prototype.initialize = function () {
	ext.bluespiceWikiFarm.ui.dialog.AddTeamMemberDialog.parent.prototype.initialize.apply( this, arguments );
	this.panel = new OO.ui.PanelLayout( {
		padded: true,
		expanded: false
	} );
	this.actions.setAbilities( { submit: false } );
	this.addItems();
	this.$body.append( this.panel.$element );
};

ext.bluespiceWikiFarm.ui.dialog.AddTeamMemberDialog.prototype.addItems = function () {
	this.userInput = new OOJSPlus.ui.widget.UserPickerWidget( {
		$overlay: this.$overlay
	} );
	this.panel.$element.append(
		new OO.ui.FieldLayout( this.userInput, {
			label: mw.message( 'wikifarm-ui-teams-field-user' ).text(),
			align: 'left'
		} ).$element
	);
	this.userInput.connect( this, {
		change: 'checkValidity',
		choose: 'checkValidity'
	} );
};

ext.bluespiceWikiFarm.ui.dialog.AddTeamMemberDialog.prototype.getActionProcess = function ( action ) {
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
			const selectedUser = this.userInput.getSelectedUser();
			if ( !selectedUser ) {
				this.popPending();
				return dfd.reject();
			}
			$.ajax( {
				url: mw.util.wikiScript( 'rest' ) + '/bluespice/farm/v1/teams/' + encodeURIComponent( this.team ) + '/assign',
				type: 'POST',
				data: JSON.stringify( {
					user: selectedUser.userWidget.user.user_name
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

			return dfd.promise();
		}, this );
	}
	return ext.bluespiceWikiFarm.ui.dialog.CreateTeamDialog.parent.prototype.getActionProcess.call( this, action );
};

ext.bluespiceWikiFarm.ui.dialog.AddTeamMemberDialog.prototype.checkValidity = function () {
	this.actions.setAbilities( { submit: !!this.userInput.getSelectedUser() } );
};

ext.bluespiceWikiFarm.ui.dialog.AddTeamMemberDialog.prototype.getBodyHeight = function () {
	if ( !this.$errors.hasClass( 'oo-ui-element-hidden' ) ) {
		return this.$element.find( '.oo-ui-processDialog-errors' )[ 0 ].scrollHeight;
	}
	return this.$element.find( '.oo-ui-window-body' )[ 0 ].scrollHeight;
};

ext.bluespiceWikiFarm.ui.dialog.AddTeamMemberDialog.prototype.showErrors = function ( errors ) {
	ext.bluespiceWikiFarm.ui.dialog.AddTeamMemberDialog.parent.prototype.showErrors.call( this, errors );
	this.updateSize();
};
