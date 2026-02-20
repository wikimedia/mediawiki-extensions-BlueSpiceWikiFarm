ext.bluespiceWikiFarm.ui.widget.InstanceColorWidget = function ( cfg ) {
	cfg = cfg || {};

	ext.bluespiceWikiFarm.ui.widget.InstanceColorWidget.super.call( this, cfg );

	this.INSTANCE_BADGE_COLORS = {
		// Blues
		'#4E79A7': true,
		'#A0CBE8': false,
		'#B8D8F0': false,

		// Greens
		'#59A14F': true,
		'#8CD17D': false,
		'#86BCB6': false,
		'#499894': true,

		// Purples
		'#B07AA1': true,
		'#D4A6C8': false,
		'#8E6C8A': true,
		'#C7B0D5': false,

		// Reds / pinks (muted)
		'#E15759': true,
		'#FF9DA7': false,
		'#D37295': true,
		'#F2B6C6': false,

		// Oranges / warm
		'#F28E2B': true,
		'#FFBE7D': false,
		'#E17C05': true,
		'#F8CFA0': false,

		// Teals / cyans
		'#76B7B2': false,

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
