ext.bluespiceWikiFarm.ui.widget.WikiAccessEntity = function ( cfg ) {
	cfg = cfg || {};
	this.type = cfg.entity_type;
	this.key = cfg.entity_key;
	this.isGlobal = cfg.is_global_assignment;

	ext.bluespiceWikiFarm.ui.widget.WikiAccessEntity.parent.call( this, {} );
	this.build();
	this.$element.addClass( 'bs-wiki-access-entity' );
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.widget.WikiAccessEntity, OO.ui.Widget );

ext.bluespiceWikiFarm.ui.widget.WikiAccessEntity.static.tagName = 'div';

ext.bluespiceWikiFarm.ui.widget.WikiAccessEntity.prototype.build = function () {
	if ( this.type === 'user' ) {
		this.$element.append( new OOJSPlus.ui.widget.UserWidget( {
			user_name: this.key // eslint-disable-line camelcase
		} ).$element );
	}
	if ( this.type === 'team' ) {
		this.$element.append( new ext.bluespiceWikiFarm.ui.widget.TeamEntity( {
			entity_key: this.key // eslint-disable-line camelcase
		} ).$element );
	}
	if ( this.isGlobal ) {
		this.$element.append( $( '<span>' ).addClass( 'bs-wiki-access-global-label' )
			.text( mw.msg( 'wikifarm-ui-access-label-global' ) ) );
	}
};
