require( './InstanceWidget.js' );

ext.bluespiceWikiFarm.ui.widget.InstanceSectionWidget = function ( cfg ) {
	cfg = cfg || {};
	ext.bluespiceWikiFarm.ui.widget.InstanceSectionWidget.super.call( this, cfg );
	this.sectionId = cfg.sectionId || '';
	this.title = cfg.title || '';
	this.elements = cfg.elements || [];
	this.emptyLabel = cfg.emptyLabel || '';
	this.sectionHasFavourite = cfg.sectionHasFavourite || false;

	this.$element.attr( 'id', 'farm-wikis-' + this.sectionId );
	this.$element.addClass( 'card card-mn' );
	this.buildHeader();
	this.buildContent();
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.widget.InstanceSectionWidget, OO.ui.Widget );

ext.bluespiceWikiFarm.ui.widget.InstanceSectionWidget.prototype.buildHeader = function () {
	this.$header = $( '<div>' )
		.addClass( 'card-header menu-title' )
		.attr( 'id', 'farm-wikis-' + this.sectionId + '-head' )
		.text( this.title );
	this.$element.append( this.$header );
};

ext.bluespiceWikiFarm.ui.widget.InstanceSectionWidget.prototype.buildContent = function () {
	if ( this.elements.length === 0 ) {
		this.$element.append( $( '<span>' ).text( this.emptyLabel ) );
		return;
	}
	for ( const i in this.elements ) {
		const element = this.elements[ i ];
		const elementWidget = new ext.bluespiceWikiFarm.ui.widget.InstanceWidget( { // eslint-disable-line mediawiki/class-doc
			color: element.instance_color,
			instanceName: element.title,
			desc: element.meta_desc,
			hasFavouriteIcon: this.sectionHasFavourite,
			isFavourite: element.favourite,
			path: element.path,
			url: element.fullurl,
			iconClass: element.iconClass || '',
			classes: element.classes || ''
		} );
		elementWidget.connect( this, { favoured: [ 'emit', 'favoured' ] } );
		this.$element.append( elementWidget.$element );
	}
};
