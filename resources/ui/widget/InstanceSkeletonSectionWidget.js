ext.bluespiceWikiFarm.ui.widget.InstanceSkeletonSectionWidget = function ( cfg ) {
	cfg = cfg || {};
	ext.bluespiceWikiFarm.ui.widget.InstanceSkeletonSectionWidget.super.call( this, cfg );

	this.sectionId = cfg.sectionId || '';
	this.count = cfg.count || 3;
	this.hasSearch = cfg.hasSearch || false;

	this.$element
		.attr( 'id', 'farm-wikis-' + this.sectionId + '-skeleton' )
		.addClass( 'card card-mn' );

	this.buildSkeleton();
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.widget.InstanceSkeletonSectionWidget, OO.ui.Widget );

/**
 * Build the full skeleton section: header, optional search bar, card items.
 */
ext.bluespiceWikiFarm.ui.widget.InstanceSkeletonSectionWidget.prototype.buildSkeleton = function () {
	// Header placeholder – same outer element as real InstanceSectionWidget
	this.$element.append(
		$( '<div>' ).addClass( 'card-header menu-title' ).append(
			$( '<div>' ).addClass( 'farm-wikis-skeleton farm-wikis-skeleton--header' )
		)
	);

	// Optional search input placeholder (used by the "other" / all-wikis section)
	if ( this.hasSearch ) {
		this.$element.append(
			$( '<div>' ).addClass( 'farm-wikis-skeleton-search-wrapper' ).append(
				$( '<div>' ).addClass( 'farm-wikis-skeleton farm-wikis-skeleton--search' )
			)
		);
	}

	// Card item placeholders
	for ( let i = 0; i < this.count; i++ ) {
		this.$element.append( this.buildSkeletonCard() );
	}
};

/**
 * Build a single skeleton card that mirrors InstanceWidget's DOM:
 *   .farm-wiki-card-item > [icon] + .farm-wiki-card-content > [name] + [desc]
 *
 * @return {jQuery}
 */
ext.bluespiceWikiFarm.ui.widget.InstanceSkeletonSectionWidget.prototype.buildSkeletonCard = function () {
	return $( '<div>' ).addClass( 'farm-wiki-card-item' ).append(
		$( '<div>' ).addClass( 'farm-wikis-skeleton farm-wikis-skeleton--icon' ),
		$( '<div>' ).addClass( 'farm-wiki-card-content' ).append(
			$( '<div>' ).addClass( 'farm-wikis-skeleton farm-wikis-skeleton--name' ),
			$( '<div>' ).addClass( 'farm-wikis-skeleton farm-wikis-skeleton--desc' )
		)
	);
};
