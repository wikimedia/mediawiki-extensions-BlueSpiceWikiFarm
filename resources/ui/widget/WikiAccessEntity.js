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
	if ( this.type === 'group' ) {
		const icon = new OO.ui.IconWidget( { icon: 'userGroup' } );
		const $label = $( '<span>' ).addClass( 'bs-access-group-name' )
			.text( this.key + ' (' + mw.msg( 'wikifarm-ui-access-assignee-type-group' ) + ')' );
		this.$element.append( icon.$element, $label );
	}
	if ( this.isGlobal ) {
		this.$element.append( $( '<span>' ).addClass( 'bs-wiki-access-global-label' )
			.text( mw.msg( 'wikifarm-ui-access-label-global' ) ) );
	}
};
