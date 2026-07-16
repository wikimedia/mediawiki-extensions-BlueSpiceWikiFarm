ext.bluespiceWikiFarm.ui.widget.InstanceWidget = function ( cfg ) {
	cfg = cfg || {};
	ext.bluespiceWikiFarm.ui.widget.InstanceWidget.super.call( this, cfg );
	this.color = cfg.color || '';
	this.instanceName = cfg.instanceName || '';
	this.desc = cfg.desc || '';
	this.hasFavouriteIcon = cfg.hasFavouriteIcon || false;
	this.isFavourite = cfg.isFavourite || false;
	this.path = cfg.path || '';
	this.url = cfg.url || '';
	this.iconClass = cfg.iconClass || '';
	this.classes = cfg.classes || '';

	this.$element.addClass( 'farm-wiki-card-item' );
	if ( this.classes.length > 0 ) {
		this.$element.addClass( this.classes );
	}
	this.buildWidget();
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.widget.InstanceWidget, OO.ui.Widget );

ext.bluespiceWikiFarm.ui.widget.InstanceWidget.prototype.buildWidget = function () {
	this.$element.attr( 'data-path', this.path );
	if ( this.color ) {
		this.$element.css( 'border-left', '3px solid ' + this.color );
	}
	if ( this.iconClass.length > 0 ) {
		this.$element.append( $( '<span>' ).addClass( this.iconClass ) );
	}

	const $desc = $( '<div>' ).addClass( 'farm-wiki-card-content' );
	$desc.append(
		$( '<a>' ).attr( 'href', this.url )
			.attr( 'title', this.instanceName )
			.text( this.instanceName )
	);
	if ( this.desc.length > 0 ) {
		$desc.append(
			$( '<span>' ).addClass( 'farm-wiki-card-desc' )
				.text( this.desc )
		);
	}
	this.$element.append( $desc );
	if ( !this.hasFavouriteIcon ) {
		return;
	}
	const favClasses = this.isFavourite ?
		'farm-wiki-card-favorite-btn bi-bs-favored wiki-instance-favored' :
		'farm-wiki-card-favorite-btn bi-bs-unfavored';

	const titleMsgKey = this.isFavourite ?
		'wikifarm-instances-favorite-remove-btn-title-label' :
		'wikifarm-instances-favorite-add-btn-title-label';

	const $favWrapper = $( '<div>' );
	const $favBtn = $( '<a>' )
		.addClass( favClasses )
		.attr( 'role', 'button' )
		.attr( 'href', '' )
		// The following messages are used here:
		// * wikifarm-instances-favorite-remove-btn-title-label
		// * wikifarm-instances-favorite-add-btn-title-label
		.attr( 'title', mw.message( titleMsgKey ).text() );

	$favBtn.on( 'click', async ( e ) => {
		e.stopPropagation();
		const action = await ext.bluespiceWikiFarm.util.toggleFavoriteInstance( this.path, this.instanceName );
		if ( action === 'add' ) {
			$( $favBtn ).toggleClass( 'bi-bs-unfavored' );
			$( $favBtn ).toggleClass( 'bi-bs-favored' );
		} else if ( action === 'remove' ) {
			$( $favBtn ).toggleClass( 'bi-bs-favored' );
			$( $favBtn ).toggleClass( 'bi-bs-unfavored' );
		}
		this.emit( 'favoured' );
	} );
	$favWrapper.append( $favBtn );
	this.$element.append( $favWrapper );
};
