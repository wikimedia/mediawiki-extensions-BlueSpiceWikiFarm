ext.bluespiceWikiFarm.ui.widget.InstanceColorWidget = function ( cfg ) {
	cfg = cfg || {};

	ext.bluespiceWikiFarm.ui.widget.InstanceColorWidget.super.call( this, cfg );

	this.INSTANCE_BADGE_COLORS = ext.bluespiceWikiFarm._config().instanceBadgeColors || []; // eslint-disable-line no-underscore-dangle

	this.colorBlocks = {};
	this.selectedColor = null;

	for ( const entry of this.INSTANCE_BADGE_COLORS ) {
		const color = entry.background;
		const darkText = !entry.lightText;
		this.colorBlocks[ color ] = $( '<div>' )
			.addClass( 'instance-color-option' )
			.css( {
				'background-color': color
			} )
			.attr( 'data-color', color )
			.on( 'click', () => {
				this.setValue( { background: color } );
				this.emit( 'select', this.getValue() );
			} );
		if ( darkText ) {
			this.colorBlocks[ color ].addClass( 'dark-text' );
		}

		this.$element.append( this.colorBlocks[ color ] );
	}

	const $resetOption = $( '<div>' )
		.addClass( 'instance-color-option default dark-text' )
		.attr( 'title', mw.msg( 'wikifarm-instance-color-remove' ) )
		.on( 'click', () => {
			this.clearValue();
			this.emit( 'select', null );
		} );
	this.$element.append( $resetOption );

	this.$element.addClass( 'instance-color-widget' );
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.widget.InstanceColorWidget, OO.ui.Widget );

ext.bluespiceWikiFarm.ui.widget.InstanceColorWidget.prototype.getValue = function () {
	if ( !this.selectedColor ) {
		return null;
	}
	const entry = this.INSTANCE_BADGE_COLORS.find( ( e ) => e.background === this.selectedColor );
	return { background: this.selectedColor, lightText: entry ? entry.lightText : true };
};

ext.bluespiceWikiFarm.ui.widget.InstanceColorWidget.prototype.setValue = function ( value ) {
	this.clearValue();
	const color = value && value.background ? value.background : null;
	if ( this.colorBlocks[ color ] ) {
		this.colorBlocks[ color ].addClass( 'selected' );
		this.selectedColor = color;
	}
};

ext.bluespiceWikiFarm.ui.widget.InstanceColorWidget.prototype.clearValue = function () {
	for ( const color in this.colorBlocks ) {
		if ( Object.prototype.hasOwnProperty.call( this.colorBlocks, color ) ) {
			this.colorBlocks[ color ].removeClass( 'selected' );
		}
	}
	this.selectedColor = null;
};
