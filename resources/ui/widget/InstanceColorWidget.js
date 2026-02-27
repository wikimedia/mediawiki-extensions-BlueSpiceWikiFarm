ext.bluespiceWikiFarm.ui.widget.InstanceColorWidget = function ( cfg ) {
	cfg = cfg || {};

	ext.bluespiceWikiFarm.ui.widget.InstanceColorWidget.super.call( this, cfg );

	this.INSTANCE_BADGE_COLORS = {
		// Blues
		'#3F6F9F': true,
		'#5A9BD5': true,
		'#4F81BD': true,

		// Greens
		'#4C8C43': true,
		'#5FAE56': true,
		'#4F9A94': true,
		'#3E7F7A': true,

		// Purples
		'#9A5D8C': true,
		'#B07AA1': true,
		'#7A5A78': true,
		'#9F86B8': true,

		// Reds / pinks
		'#C94C4E': true,
		'#E06C75': true,
		'#B85C82': true,
		'#D98DA4': false,

		// Oranges
		'#D9791F': true,
		'#E89A4C': true,
		'#C96500': true,
		'#E5A96E': false,

		// Teals
		'#5FA7A3': true,

		_default: true
	};

	this.colorBlocks = {};
	this.selectedColor = null;

	for ( const color in this.INSTANCE_BADGE_COLORS ) {
		let darkText = true;
		if ( this.INSTANCE_BADGE_COLORS[ color ] ) {
			darkText = false;
		}
		if ( color === '_default' ) {
			this.colorBlocks[ color ] = $( '<div>' )
				.addClass( 'instance-color-option default dark-text' )
				.attr( 'title', mw.msg( 'wikifarm-instance-color-remove' ) )
				.on( 'click', () => {
					this.clearValue();
					this.emit( 'select', null );
				} );
		} else {
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
		}

		this.$element.append( this.colorBlocks[ color ] );
	}

	this.$element.addClass( 'instance-color-widget' );
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.widget.InstanceColorWidget, OO.ui.Widget );

ext.bluespiceWikiFarm.ui.widget.InstanceColorWidget.prototype.getValue = function () {
	if ( !this.selectedColor ) {
		return null;
	}
	return { background: this.selectedColor, lightText: this.INSTANCE_BADGE_COLORS[ this.selectedColor ] };
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
