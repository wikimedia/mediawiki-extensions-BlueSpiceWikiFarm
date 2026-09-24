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
	const headingId = OO.ui.generateElementId();
	this.$heading = $( '<p>' )
		.attr( 'id', headingId )
		.text( mw.msg( 'wikifarm-access-general-heading' ) );

	this.cards = {};
	this.$cardsContainer = $( '<div>' )
		.addClass( 'bs-access-level-cards' )
		.attr( {
			role: 'radiogroup',
			'aria-labelledby': headingId
		} );
	this.inputs = {};
	const inputName = OO.ui.generateElementId();

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
		const titleId = OO.ui.generateElementId();
		const descId = OO.ui.generateElementId();
		const $input = $( '<input>' )
			.addClass( 'bs-access-level-card-input' )
			.attr( {
				type: 'radio',
				name: inputName,
				value: level.key,
				'aria-labelledby': titleId,
				'aria-describedby': descId
			} )
			.prop( 'disabled', level.disabled );
		$input.on( 'change', () => this.selectLevel( level.key ) );

		const icon = new OO.ui.IconWidget( { icon: level.icon } );
		const $header = $( '<span>' ).addClass( 'bs-access-level-card-header' );
		const $title = $( '<span>' )
			.addClass( 'bs-access-level-card-title' )
			.attr( 'id', titleId )
			.text( level.label );
		const $desc = $( '<span>' )
			.addClass( 'bs-access-level-card-desc' )
			.attr( 'id', descId )
			.html( level.description );

		$header.append( icon.$element, $title );
		const $card = $( '<label>' )
			.addClass( 'bs-access-level-card' )
			.toggleClass( 'bs-access-level-card-disabled', level.disabled )
			.attr( 'data-level', level.key )
			.append( $input, $header, $desc );

		this.cards[ level.key ] = $card;
		this.inputs[ level.key ] = $input;
		this.$cardsContainer.append( $card );
	}

	this.saveButton = new OO.ui.ButtonWidget( {
		label: mw.msg( 'wikifarm-button-action-label-save' ),
		flags: [ 'primary', 'progressive' ],
		disabled: true
	} );
	this.saveButton.connect( this, { click: 'onSave' } );
	this.selectLevel( this.accessLevel );

	this.toolbar = new OO.ui.HorizontalLayout( {
		classes: [ 'bs-access-management-toolbar' ],
		items: [ this.saveButton ]
	} );

	this.$element.append( this.toolbar.$element, this.$heading, this.$cardsContainer );
};

ext.bluespiceWikiFarm.ui.GeneralSettingsPanel.prototype.selectLevel = function ( level ) {
	this.accessLevel = level;
	for ( const key in this.cards ) {
		this.cards[ key ].toggleClass( 'bs-access-level-card-selected', key === level );
		this.inputs[ key ].prop( 'checked', key === level );
	}
	this.saveButton.setDisabled( this.accessLevel === this.savedAccessLevel );
};

ext.bluespiceWikiFarm.ui.GeneralSettingsPanel.prototype.onSave = function () {
	this.saveButton.setDisabled( true );

	const api = new mw.Rest();
	const data = { WikiFarmAccessLevel: this.accessLevel };
	bs.api.tasks.exec( 'configmanager', 'save', data )
		.done( ( response ) => {
			if ( !response.hasOwnProperty( 'success' ) || !response.success ) {
				OO.ui.alert( mw.msg( 'wikifarm-ui-access-error-set-level' ) );
				this.saveButton.setDisabled( false );
				return;
			}
			api.post( '/bluespice/farm/v1/access/level', { level: this.accessLevel } )
				.done( ( accessResponse ) => {
					if ( !accessResponse.hasOwnProperty( 'success' ) || !accessResponse.success ) {
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
