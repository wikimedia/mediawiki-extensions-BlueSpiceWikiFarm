ext.bluespiceWikiFarm.ui.GeneralSettingsPanel = function ( cfg ) {
	cfg = cfg || {};
	this.accessLevel = cfg.accessLevel || 'private';
	this.savedAccessLevel = this.accessLevel;
	this.alwaysVisible = cfg.alwaysVisible || false;

	ext.bluespiceWikiFarm.ui.GeneralSettingsPanel.parent.call( this, {
		padded: true,
		expanded: false
	} );

	this.$element.addClass( 'bs-access-management-general' );
	this.build();
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.GeneralSettingsPanel, OO.ui.PanelLayout );

ext.bluespiceWikiFarm.ui.GeneralSettingsPanel.prototype.build = function () {
	this.$element.append(
		$( '<h6>' ).text( mw.msg( 'wikifarm-access-general-heading' ) )
	);

	this.cards = {};
	this.$cardsContainer = $( '<div>' ).addClass( 'bs-access-level-cards' );

	const levels = [
		{
			key: 'public',
			label: mw.msg( 'wikifarm-ui-access-wiki-type-public' ),
			description: mw.msg( 'wikifarm-access-card-public-desc' ),
			icon: 'globe',
			disabled: false
		},
		{
			key: 'protected',
			label: mw.msg( 'wikifarm-ui-access-wiki-type-protected' ),
			description: mw.msg( 'wikifarm-access-card-protected-desc' ),
			icon: 'editLock',
			disabled: false
		},
		{
			key: 'private',
			label: mw.msg( 'wikifarm-ui-access-wiki-type-private' ),
			description: mw.msg( 'wikifarm-access-card-private-desc' ),
			icon: 'lock',
			disabled: this.alwaysVisible
		}
	];

	for ( const level of levels ) {
		const $card = $( '<div>' )
			.addClass( 'bs-access-level-card' )
			.toggleClass( 'bs-access-level-card-selected', level.key === this.accessLevel )
			.toggleClass( 'bs-access-level-card-disabled', level.disabled )
			.attr( 'data-level', level.key );

		const icon = new OO.ui.IconWidget( { icon: level.icon } );
		const $header = $( '<div>' ).addClass( 'bs-access-level-card-header' );
		const $title = $( '<div>' ).addClass( 'bs-access-level-card-title' ).text( level.label );
		const $desc = $( '<div>' ).addClass( 'bs-access-level-card-desc' ).text( level.description );

		$header.append( icon.$element, $title );
		$card.append( $header, $desc );

		if ( !level.disabled ) {
			$card.on( 'click', ( e ) => {
				const selectedLevel = $( e.currentTarget ).attr( 'data-level' );
				this.selectLevel( selectedLevel );
			} );
		}

		this.cards[ level.key ] = $card;
		this.$cardsContainer.append( $card );
	}

	this.saveButton = new OO.ui.ButtonWidget( {
		label: mw.msg( 'wikifarm-button-action-label-save' ),
		flags: [ 'primary', 'progressive' ],
		disabled: true
	} );
	this.saveButton.connect( this, { click: 'onSave' } );

	this.$element.append( this.$cardsContainer, this.saveButton.$element );
};

ext.bluespiceWikiFarm.ui.GeneralSettingsPanel.prototype.selectLevel = function ( level ) {
	this.$cardsContainer.find( '.bs-access-level-card' ).removeClass( 'bs-access-level-card-selected' );
	this.cards[ level ].addClass( 'bs-access-level-card-selected' );
	this.accessLevel = level;
	this.saveButton.setDisabled( this.accessLevel === this.savedAccessLevel );
};

ext.bluespiceWikiFarm.ui.GeneralSettingsPanel.prototype.onSave = function () {
	this.saveButton.setDisabled( true );

	const data = { WikiFarmAccessLevel: this.accessLevel };
	bs.api.tasks.exec( 'configmanager', 'save', data )
		.done( ( response ) => {
			if ( !response.hasOwnProperty( 'success' ) || !response.success ) {
				OO.ui.alert( mw.msg( 'wikifarm-ui-access-error-set-level' ) );
				this.saveButton.setDisabled( false );
				return;
			}
			this.savedAccessLevel = this.accessLevel;
			mw.notify( mw.msg( 'wikifarm-ui-access-success-set-level' ) );
		} ).fail( () => {
			OO.ui.alert( mw.msg( 'wikifarm-ui-access-error-set-level' ) );
			this.saveButton.setDisabled( false );
		} );
};

ext.bluespiceWikiFarm.ui.GeneralSettingsPanel.prototype.hasUnsavedChanges = function () {
	return this.accessLevel !== this.savedAccessLevel;
};

ext.bluespiceWikiFarm.ui.GeneralSettingsPanel.prototype.resetChanges = function () {
	this.selectLevel( this.savedAccessLevel );
};
